<?php

namespace IAWP;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Favicon\Favicon;
use IAWP\Favicon\FaviconDownloader;
use IAWP\Utils\Server;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class FetchFaviconsJob extends \IAWP\Cron_Job
{
    protected $name = 'iawp_fetch_favicons';
    protected $interval = 'daily';
    protected $at_midnight = \true;
    public function handle() : void
    {
        Server::increase_max_execution_time();
        $this->update_cached_favicons();
    }
    private function update_cached_favicons() : void
    {
        $directory = \IAWPSCOPED\iawp_upload_path_to('iawp-favicons');
        if (!\file_exists($directory)) {
            \wp_mkdir_p($directory);
        }
        $query = \IAWP\Illuminate_Builder::new()->select(['sessions.referrer_id', 'referrers.domain'])->selectRaw('COUNT(*) AS total')->from(\IAWP\Tables::sessions(), 'sessions')->join(\IAWP\Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id')->where('sessions.created_at', '>=', CarbonImmutable::now()->subDays(30)->toDate())->groupBy('referrer_id')->orderByDesc('total')->limit(200);
        $favicons = $query->get()->map(fn($item) => Favicon::for($item->domain));
        $required_files = $favicons->map(fn($favicon) => $favicon->file_name());
        $current_paths = \glob($directory . "/*.png");
        $current_files = \array_map(fn($path) => \basename($path), $current_paths);
        $images_to_delete = Collection::make($current_files)->diff($required_files);
        foreach ($images_to_delete as $image) {
            $path = \IAWPSCOPED\iawp_upload_path_to('iawp-favicons/' . $image);
            if (\file_exists($path)) {
                \unlink($directory . '/' . $image);
            }
        }
        foreach ($favicons as $favicon) {
            if (!$favicon->exists()) {
                FaviconDownloader::for($favicon)->download();
            }
        }
    }
}
