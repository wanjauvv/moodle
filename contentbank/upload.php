<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upload a file to content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once("$CFG->dirroot/contentbank/files_form.php");

require_login();

$contextid = optional_param('contextid', \context_system::instance()->id, PARAM_INT);
$context = context::instance_by_id($contextid, MUST_EXIST);

require_capability('moodle/contentbank:upload', $context);

$title = get_string('contentbank');
\core_contentbank\helper::get_page_ready($context, $title, true);
if ($PAGE->course) {
    require_login($PAGE->course->id);
}
$returnurl = new \moodle_url('/contentbank/index.php', ['contextid' => $contextid]);

$PAGE->set_url('/contentbank/upload.php');
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('upload', 'contentbank'));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagetype('contenbank');

$maxbytes = $CFG->userquota;
$maxareabytes = $CFG->userquota;
if (has_capability('moodle/user:ignoreuserquota', $context)) {
    $maxbytes = USER_CAN_IGNORE_FILE_SIZE_LIMITS;
    $maxareabytes = FILE_AREA_MAX_BYTES_UNLIMITED;
}

$cb = new \core_contentbank\contentbank();
$accepted = $cb->get_supported_extensions_as_string($context);

$data = new stdClass();
$options = array(
    'subdirs' => 1,
    'maxbytes' => $maxbytes,
    'maxfiles' => -1,
    'accepted_types' => $accepted,
    'areamaxbytes' => $maxareabytes
);
file_prepare_standard_filemanager($data, 'files', $options, $context, 'contentbank', 'public', 0);

$mform = new contentbank_files_form(null, ['contextid' => $contextid, 'data' => $data, 'options' => $options]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    require_sesskey();

    // Get the file and the contenttype to manage given file's extension.
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $formdata->file, 'itemid, filepath, filename', false);

    if (!empty($files)) {
        $file = reset($files);
        $filename = $file->get_filename();
        $extension = $cb->get_extension($filename);
        $plugin = $cb->get_extension_supporter($extension, $context);
        $classname = '\\contenttype_'.$plugin.'\\contenttype';
        $record = new stdClass();
        $record->name = $filename;
        if (class_exists($classname)) {
            $contentype = new $classname($context);
            $content = $contentype->create_content($record);
            file_save_draft_area_files($formdata->file, $contextid, 'contentbank', 'public', $content->get_id());
        }
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
