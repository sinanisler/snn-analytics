@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Report_Finder $report_finder */ @endphp
@php /** @var bool $is_white_labeled */ @endphp
@php /** @var bool $can_edit_settings */ @endphp
@php /** @var bool $is_dark_mode */ @endphp

<div id="iawp-layout-sidebar" class="iawp-layout-sidebar">
    <div class="inner"><?php
        if (!$is_white_labeled) : ?>
            <div class="logo">
                <img class="full-logo"
                     src="<?php echo esc_url(iawp_url_to('img/logo.png')); ?>"
                     data-testid="logo"/>
                <img class="favicon" src="<?php echo esc_url(iawp_url_to('img/favicon.png')); ?>"
                     data-testid="favicon"/>
            </div><?php
        endif;
        if ($env->is_free() && !$is_white_labeled) : ?>
            <div class="pro-ad">
                <a href="https://independentwp.com/pro/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Upgrade+to+Pro&utm_content=Sidebar"
                   target="_blank">
                    <span class="upgrade-text"><?php esc_html_e('Upgrade to Pro', 'independent-analytics'); ?></span>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </a>
            </div><?php
        endif; ?>

        <div class="mobile-menu">
            <button id="mobile-menu-toggle" class="mobile-menu-toggle iawp-button ghost-purple">
                <span class="dashicons dashicons-menu"></span> <span class="text"><?php
                    esc_html_e('Open menu', 'independent-analytics'); ?>
                </span>
            </button>
        </div>
        <div id="menu-container" class="menu-container">
            <div class="reports-list">
                <?php
                // OVERVIEW
                if ($env->is_pro() && $can_view_all_analytics) {
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Overview', 'independent-analytics'),
                        'report_type'       => 'overview',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__('Overview report', 'independent-analytics'),
                        'supports_saved_reports' => false,
                        'url'               => iawp_dashboard_url(['tab' => 'overview']),
                        'external'          => false,
                        'upgrade'           => false
                    ]);
                }
                // REAL-TIME
                if ($env->is_pro()) {
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Real-time', 'independent-analytics'),
                        'report_type'       => 'real-time',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__('Real-time report','independent-analytics'),
                        'supports_saved_reports' => false,
                        'url'               => iawp_dashboard_url(['tab' => 'real-time']),
                        'external'          => false,
                        'upgrade'           => false
                    ]);
                }
                // JOURNEYS
                if ($env->is_pro() && $can_view_all_analytics) {
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('User Journeys', 'independent-analytics'),
                        'report_type'       => 'journeys',
                        'reports'           => $report_finder->get_saved_reports_for_type('journeys'),
                        'collapsed_label'   => '',
                        'supports_saved_reports' => true,
                        'url'               => iawp_dashboard_url(['tab' => 'journeys']),
                        'external'          => false,
                        'upgrade'           => false
                    ]);
                }
                // PAGES
                echo iawp_blade()->run('partials.sidebar-menu-section', [
                    'can_edit_settings' => $can_edit_settings,
                    'report_name'       => esc_html__('Pages', 'independent-analytics'),
                    'report_type'       => 'views',
                    'reports'           => $report_finder->get_saved_reports_for_type('views'),
                    'collapsed_label'   => '',
                    'supports_saved_reports' => true,
                    'url'               => iawp_dashboard_url(['tab' => 'views']),
                    'external'          => false,
                    'upgrade'           => false
                ]);
                // REFERRERS
                echo iawp_blade()->run('partials.sidebar-menu-section', [
                    'can_edit_settings' => $can_edit_settings,
                    'report_name'       => esc_html__('Referrers', 'independent-analytics'),
                    'report_type'       => 'referrers',
                    'reports'           => $report_finder->get_saved_reports_for_type('referrers'),
                    'collapsed_label'   => '',
                    'supports_saved_reports' => true,
                    'url'               => iawp_dashboard_url(['tab' => 'referrers']),
                    'external'          => false,
                    'upgrade'           => false
                ]);
                // GEOGRAPHIC
                echo iawp_blade()->run('partials.sidebar-menu-section', [
                    'can_edit_settings' => $can_edit_settings,
                    'report_name'       => esc_html__('Geographic', 'independent-analytics'),
                    'report_type'       => 'geo',
                    'reports'           => $report_finder->get_saved_reports_for_type('geo'),
                    'collapsed_label'   => '',
                    'supports_saved_reports' => true,
                    'url'               => iawp_dashboard_url(['tab' => 'geo']),
                    'external'          => false,
                    'upgrade'           => false
                ]);
                // DEVICES
                echo iawp_blade()->run('partials.sidebar-menu-section', [
                    'can_edit_settings' => $can_edit_settings,
                    'report_name'       => esc_html__('Devices', 'independent-analytics'),
                    'report_type'       => 'devices',
                    'reports'           => $report_finder->get_saved_reports_for_type('devices'),
                    'collapsed_label'   => '',
                    'supports_saved_reports' => true,
                    'url'               => iawp_dashboard_url(['tab' => 'devices']),
                    'external'          => false,
                    'upgrade'           => false
                ]);
                // CAMPAIGNS
                if ($env->is_pro()) {
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Campaigns', 'independent-analytics'),
                        'report_type'       => 'campaigns',
                        'reports'           => $report_finder->get_saved_reports_for_type('campaigns'),
                        'collapsed_label'   => '',
                        'supports_saved_reports' => true,
                        'url'               => iawp_dashboard_url(['tab' => 'campaigns']),
                        'external'          => false,
                        'upgrade'           => false
                    ]);
                }
                // CLICKS
                if ($env->is_pro()) {
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Clicks', 'independent-analytics'),
                        'report_type'       => 'clicks',
                        'reports'           => $report_finder->get_saved_reports_for_type('clicks'),
                        'collapsed_label'   => '',
                        'supports_saved_reports' => true,
                        'url'               => iawp_dashboard_url(['tab' => 'clicks']),
                        'external'          => false,
                        'upgrade'           => false
                    ]);
                }
                if ($env->is_free() && ! $env->is_white_labeled()) {
                    // OVERVIEW UPGRADE
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Overview', 'independent-analytics'),
                        'report_type'       => 'overview',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__(
                            'Get Overview',
                            'independent-analytics'
                        ),
                        'supports_saved_reports' => false,
                        'url'               => 'https://independentwp.com/features/overview-report/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Overview+menu+item&utm_content=Sidebar',
                        'external'          => true,
                        'upgrade'           => true
                    ]);
                    // USER JOURNEYS UPGRADE
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('User Journeys', 'independent-analytics'),
                        'report_type'       => 'journeys',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__(
                            'Get user journeys',
                            'independent-analytics'
                        ),
                        'supports_saved_reports' => false,
                        'url'               => 'https://independentwp.com/features/user-journeys/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=User+Journeys+menu+item&utm_content=Sidebar',
                        'external'          => true,
                        'upgrade'           => true
                    ]);
                    // CAMPAIGNS UPGRADE
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Campaigns', 'independent-analytics'),
                        'report_type'       => 'campaigns',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__(
                            'Get Campaigns',
                            'independent-analytics'
                        ),
                        'supports_saved_reports' => false,
                        'url'               => 'https://independentwp.com/features/campaigns/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Campaigns+menu+item&utm_content=Sidebar',
                        'external'          => true,
                        'upgrade'           => true
                    ]);
                    // CLICKS UPGRADE
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Clicks', 'independent-analytics'),
                        'report_type'       => 'clicks',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__(
                            'Get click tracking',
                            'independent-analytics'
                        ),
                        'supports_saved_reports' => false,
                        'url'               => 'https://independentwp.com/features/click-tracking/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Clicks+menu+item&utm_content=Sidebar',
                        'external'          => true,
                        'upgrade'           => true
                    ]);
                    // REAL-TIME UPGRADE
                    echo iawp_blade()->run('partials.sidebar-menu-section', [
                        'can_edit_settings' => $can_edit_settings,
                        'report_name'       => esc_html__('Real-Time', 'independent-analytics'),
                        'report_type'       => 'real-time-free',
                        'reports'           => null,
                        'collapsed_label'   => esc_html__(
                            'Get Real-time analytics',
                            'independent-analytics'
                        ),
                        'supports_saved_reports' => false,
                        'url'               => 'https://independentwp.com/features/real-time/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Real-time+menu+item&utm_content=Sidebar',
                        'external'          => true,
                        'upgrade'           => true
                    ]);
                } ?>
            </div>
        </div>
        <div class="collapse-container">
            <button id="collapse-sidebar" class="collapse-sidebar iawp-text-button"
                    data-testid="collapse-button"><span
                        class="dashicons dashicons-admin-collapse"></span><span
                        class="text"><?php esc_html_e('Collapse sidebar', 'independent-analytics'); ?></span>
            </button>
            <span class="collapsed-label"><?php esc_html_e('Expand sidebar', 'independent-analytics'); ?></span>
        </div>
    </div>
</div>
