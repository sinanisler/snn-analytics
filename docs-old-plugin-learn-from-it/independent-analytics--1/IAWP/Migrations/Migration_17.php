<?php

namespace IAWP\Migrations;

use IAWP\Report_Finder;
/** @internal */
class Migration_17 extends \IAWP\Migrations\Migration
{
    /**
     * @inheritdoc
     */
    protected $database_version = '17';
    /**
     * @inheritDoc
     */
    protected function migrate() : void
    {
        try {
            Report_Finder::insert_default_reports();
        } catch (\Throwable $e) {
        }
    }
}
