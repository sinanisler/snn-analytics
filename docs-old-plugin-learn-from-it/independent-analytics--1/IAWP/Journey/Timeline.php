<?php

namespace IAWP\Journey;

use IAWP\Journey\Events\Click;
use IAWP\Journey\Events\Event;
use IAWP\Journey\Events\Order;
use IAWP\Journey\Events\Origin;
use IAWP\Journey\Events\Submission;
use IAWP\Journey\Events\View;
use IAWP\Utils\Timezone;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Timeline
{
    private Origin $origin;
    /** @var Event[] */
    private array $events;
    public function __construct(int $session_id)
    {
        $this->origin = Origin::from_session($session_id);
        $this->events = [$this->origin, ...$this->fetch_events($session_id)];
    }
    public function origin() : Origin
    {
        return $this->origin;
    }
    public function visitor_url() : string
    {
        return \admin_url('admin.php?page=independent-analytics-visitor&visitor=' . $this->origin->visitor_id());
    }
    public function session_count_message() : string
    {
        $sessions = $this->origin()->session_count();
        if ($sessions === 1) {
            return \__('No other sessions for this visitor', 'independent-analytics');
        } else {
            return \sprintf(\__('View all %d sessions for this visitor', 'independent-analytics'), $sessions);
        }
    }
    public function created_at_for_humans() : string
    {
        return $this->origin->created_at()->timezone(Timezone::site_timezone())->format(WordPress_Site_Date_Format_Pattern::for_php());
    }
    public function events()
    {
        return $this->events;
    }
    public function conversion_events()
    {
        $types = ['order', 'click', 'submission'];
        return Collection::make($this->events)->filter(fn(Event $event) => \in_array($event->type(), $types))->values()->all();
    }
    private function fetch_events(int $session_id) : array
    {
        $views = Collection::make(View::from_session($session_id));
        $first_view = $views->shift();
        $events = Collection::make($views)->merge(Order::from_session($session_id))->merge(Submission::from_session($session_id))->merge(Click::from_session($session_id))->sortBy(function (Event $event) {
            $priorities = ['click' => 0, 'submission' => 1, 'order' => 2, 'view' => 3];
            return [$event->created_at()->timestamp, $priorities[$event->type()] ?? 4];
        });
        if ($first_view) {
            $events->prepend($first_view);
        }
        return $events->all();
    }
}
