<?php

namespace IAWP\Email_Reports;

use DateTime;
use IAWP\Rows\Campaigns;
use IAWP\Rows\Countries;
use IAWP\Rows\Device_Types;
use IAWP\Rows\Forms;
use IAWP\Rows\Link_Patterns;
use IAWP\Rows\Pages;
use IAWP\Rows\Referrers;
use IAWP\Sort_Configuration;
use IAWP\Statistics\Page_Statistics;
use IAWP\Statistics\Statistic;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Table_Campaigns;
use IAWP\Tables\Table_Clicks;
use IAWP\Tables\Table_Devices;
use IAWP\Tables\Table_Geo;
use IAWP\Tables\Table_Pages;
use IAWP\Tables\Table_Referrers;
use IAWP\Utils\Timezone;
use IAWP\Utils\URL;
/** @internal */
class Email_Reports
{
    public function __construct()
    {
        $monitored_options = ['iawp_email_report_interval', 'iawp_email_report_time', 'iawp_email_report_email_addresses', 'iawp_email_report_paused'];
        foreach ($monitored_options as $option) {
            \add_action('update_option_' . $option, [$this, 'schedule'], 10, 0);
            \add_action('add_option_' . $option, [$this, 'schedule'], 10, 0);
        }
        // Maybe reschedule when starting day of the week is changed
        \add_action('update_option_iawp_dow', [$this, 'maybe_reschedule'], 10, 0);
        \add_action('add_option_iawp_dow', [$this, 'maybe_reschedule'], 10, 0);
        \add_action('iawp_send_email_report', [$this, 'send_email_report']);
    }
    public function schedule()
    {
        \wp_unschedule_hook('iawp_send_email_report');
        if (\get_option('iawp_email_report_paused', '0') === '1') {
            return;
        }
        if (empty(\IAWPSCOPED\iawp()->get_option('iawp_email_report_email_addresses', []))) {
            return;
        }
        \wp_schedule_event($this->interval()->next_interval_start()->getTimestamp(), $this->interval()->id(), 'iawp_send_email_report');
    }
    /**
     * For testing purposes, get the
     *
     * @return DateTime
     */
    public function next_event_scheduled_at() : ?DateTime
    {
        if (!\wp_next_scheduled('iawp_send_email_report')) {
            return null;
        }
        $date = new DateTime();
        $date->setTimezone(Timezone::site_timezone());
        $date->setTimestamp(\wp_next_scheduled('iawp_send_email_report'));
        return $date;
    }
    public function next_email_at_for_humans() : string
    {
        if (!\wp_next_scheduled('iawp_send_email_report')) {
            return \esc_html__('There is no email scheduled.', 'independent-analytics');
        }
        $date = $this->interval()->next_interval_start();
        $day = $date->format(\get_option('date_format'));
        $time = $date->format(\IAWPSCOPED\iawp()->get_option('time_format', 'g:i a'));
        return \sprintf(\__('Next email scheduled for %s at %s.', 'independent-analytics'), '<span>' . $day . '</span>', '<span>' . $time . '</span>');
    }
    public function maybe_reschedule()
    {
        if (!\wp_next_scheduled('iawp_send_email_report')) {
            return;
        }
        if (\IAWPSCOPED\iawp()->get_option('iawp_email_report_interval', 'monthly') != 'weekly') {
            return;
        }
        $this->schedule();
    }
    public function send_email_report(bool $is_test_email = \false)
    {
        // Email reports should be scheduled every time. CRON jobs run on a fixed interval which cannot
        // account for daylight saving times changes, causing them to send at the wrong hour as daylight
        // savings time changes. Months also have a varying number of days and need to be rescheduled
        // to consistently send on the correct day.
        $this->schedule();
        $to = \IAWPSCOPED\iawp()->get_option('iawp_email_report_email_addresses', []);
        if (empty($to)) {
            return;
        }
        if (\get_option('iawp_email_report_paused', '0') === '1') {
            return;
        }
        $from = \IAWPSCOPED\iawp()->get_option('iawp_email_report_from_address', \get_option('admin_email'));
        $reply_to = \IAWPSCOPED\iawp()->get_option('iawp_email_report_reply_to_address', \get_option('admin_email'));
        $body = $this->get_email_body();
        if (\count($to) > 1) {
            for ($i = 0; $i < \count($to); $i++) {
                if ($i == 0) {
                    continue;
                }
                $headers[] = 'Bcc: ' . $to[$i];
            }
        }
        $headers[] = 'From: ' . \get_bloginfo('name') . ' <' . \esc_attr($from) . '>';
        $headers[] = 'Reply-To: ' . \esc_attr($reply_to);
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        // Prevents WP HTML Mail plugin from breaking email design (https://wordpress.org/plugins/wp-html-mail/)
        \add_filter('haet_mail_use_template', function () {
            return \false;
        });
        return \wp_mail($to[0], $this->subject_line($is_test_email), $body, $headers);
    }
    public function get_email_body($colors = '')
    {
        $statistics = new Page_Statistics($this->interval()->date_range());
        $quick_stats = \array_values(\array_filter($statistics->get_statistics(), function (Statistic $statistics) {
            return $statistics->is_visible() && $statistics->is_group_plugin_enabled();
        }));
        $chart = new \IAWP\Email_Reports\Email_Chart($statistics);
        $colors = $colors == '' ? \IAWPSCOPED\iawp()->get_option('iawp_email_report_colors', ['#5123a0', '#fafafa', '#3a1e6b', '#fafafa', '#5123a0', '#a985e6', '#ece9f2', '#f7f5fa', '#ece9f2', '#dedae6']) : \explode(',', $colors);
        $footer_text = \IAWPSCOPED\iawp()->get_option('iawp_email_report_footer', \sprintf(\esc_html__('This email was generated and delivered by %s', 'independent-analytics'), \esc_url(\get_site_url())));
        return \IAWPSCOPED\iawp_blade()->run('email.email', [
            'site_title' => \get_bloginfo('name'),
            'site_url' => URL::new(\get_site_url())->get_domain(),
            'date' => $this->interval()->report_time_period_for_humans(),
            // The value that needs to change
            'stats' => $quick_stats,
            'top_ten' => $this->get_top_ten(),
            'chart_views' => $chart->views,
            'chart_title' => $this->interval()->chart_title(),
            'most_views' => $chart->most_views,
            'y_labels' => $chart->y_labels,
            'x_labels' => $chart->x_labels,
            'colors' => $colors,
            'footer_text' => $footer_text,
        ]);
    }
    private function interval() : \IAWP\Email_Reports\Interval
    {
        return \IAWP\Email_Reports\Interval_Factory::from_option();
    }
    private function subject_line(bool $is_test_email) : string
    {
        $parts = [];
        if ($is_test_email) {
            $parts[] = \__('[Test]', 'independent-analytics');
        }
        $parts[] = \__('Analytics Report for', 'independent-analytics');
        $parts[] = \get_bloginfo('name');
        $parts[] = '[' . $this->interval()->report_time_period_for_humans() . ']';
        return \esc_html(\implode(' ', $parts));
    }
    private function get_top_ten() : array
    {
        $date_range = $this->interval()->date_range();
        $queries = ['pages' => 'title', 'referrers' => 'referrer', 'countries' => 'country', 'devices' => 'device_type', 'campaigns' => 'title', 'forms' => 'form_title', 'clicks' => 'link_name', 'landing_pages' => 'title', 'exit_pages' => 'title'];
        $top_ten = [];
        $title = '';
        foreach ($queries as $type => $title) {
            if ($type === 'pages') {
                $pages_table = new Table_Pages();
                $query = new Pages($date_range, $pages_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Pages', 'independent-analytics');
            } elseif ($type === 'referrers') {
                $referrers_table = new Table_Referrers();
                $query = new Referrers($date_range, $referrers_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Referrers', 'independent-analytics');
            } elseif ($type === 'countries') {
                $geo_table = new Table_Geo();
                $query = new Countries($date_range, $geo_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Countries', 'independent-analytics');
            } elseif ($type === 'devices') {
                $devices_table = new Table_Devices();
                $query = new Device_Types($date_range, $devices_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Devices', 'independent-analytics');
            } elseif ($type === 'campaigns') {
                $campaigns_table = new Table_Campaigns();
                $query = new Campaigns($date_range, $campaigns_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Campaigns', 'independent-analytics');
            } elseif ($type === 'forms') {
                // This is a special case for form submissions which doesn't have an associated table
                $column = new Column(['id' => 'submissions', 'name' => \__('Submissions', 'independent-analytics'), 'type' => 'int']);
                $sort_configuration = new Sort_Configuration($column);
                $query = new Forms($date_range, $sort_configuration, 10);
                $title = \esc_html__('Forms', 'independent-analytics');
            } elseif ($type === 'clicks') {
                $clicks_table = new Table_Clicks();
                $query = new Link_Patterns($date_range, $clicks_table->sanitize_sort_parameters(), 10);
                $title = \esc_html__('Link Patterns', 'independent-analytics');
            } elseif ($type === 'landing_pages') {
                $pages_table = new Table_Pages();
                $query = new Pages($date_range, $pages_table->sanitize_sort_parameters('entrances'), 10);
                $title = \esc_html__('Landing Pages', 'independent-analytics');
            } elseif ($type === 'exit_pages') {
                $pages_table = new Table_Pages();
                $query = new Pages($date_range, $pages_table->sanitize_sort_parameters('exits'), 10);
                $title = \esc_html__('Exit Pages', 'independent-analytics');
            } else {
                continue;
            }
            $rows = \array_map(function ($row, $index) use($type) {
                if ($type == 'referrers') {
                    $edited_title = $row->referrer();
                } elseif ($type == 'countries') {
                    $edited_title = $row->country();
                } elseif ($type == 'devices') {
                    $edited_title = $row->device_type();
                } elseif ($type == 'campaigns') {
                    $edited_title = $row->utm_campaign();
                } elseif ($type == 'forms') {
                    $edited_title = $row->form_title();
                } elseif ($type == 'clicks') {
                    $edited_title = $row->link_name();
                } else {
                    $edited_title = $row->title();
                }
                $edited_title = \mb_strlen($edited_title) > 30 ? \mb_substr($edited_title, 0, 30) . '...' : $edited_title;
                $metric = 'views';
                if ($type == 'clicks') {
                    $metric = 'link_clicks';
                } elseif ($type == 'forms') {
                    $metric = 'submissions';
                } elseif ($type == 'landing_pages') {
                    $metric = 'entrances';
                } elseif ($type == 'exit_pages') {
                    $metric = 'exits';
                }
                return ['title' => $edited_title, 'views' => $row->{$metric}()];
            }, $query->rows(), \array_keys($query->rows()));
            if (\count($rows) == 0) {
                continue;
            }
            // Remove landing page and exit rows with 0 entrances/exits
            $rows = \array_filter($rows, function ($row) {
                return $row['views'] > 0;
            });
            $top_ten[$type] = ['title' => $title, 'rows' => $rows];
        }
        return $top_ten;
    }
}
