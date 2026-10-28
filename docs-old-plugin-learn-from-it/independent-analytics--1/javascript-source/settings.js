import {UserRoles} from './modules/user-roles';
import {FieldDuplicator} from './modules/duplicate-field';
import {EmailReports} from './modules/email-reports';
import {downloadCSV} from './download'

jQuery(function ($) {
    UserRoles.setup();
    FieldDuplicator.setup();
    EmailReports.setup();
});
