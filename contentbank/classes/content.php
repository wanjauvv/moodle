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
 * Content manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

use stored_file;
use stdClass;
use coding_exception;
use moodle_url;

/**
 * Content manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class content {

    /** @var stdClass $content The content of the current instance. **/
    protected $content  = null;

    /**
     * Content bank constructor
     *
     * @param stdClass $content A contentbanck_content record.
     * @throws coding_exception If content type is not right.
     */
    public function __construct(stdClass $content) {
        // Content type should exist and be linked to plugin classname.
        $classname = $content->contenttype.'\\content';
        if (get_class($this) != $classname) {
            throw new coding_exception(get_string('contenttypenotfound', 'error', $content->contenttype));
        }
        $typeclass = $content->contenttype.'\\contenttype';
        if (!class_exists($typeclass)) {
            throw new coding_exception(get_string('contenttypenotfound', 'error', $content->contenttype));
        }
        // A record with the id must exist in 'contenbank_content' table.
        // To improve performance, we are only checking the id is set, but no querying the database.
        if (!isset($content->id)) {
            throw new coding_exception(get_string('invalidcontentid', 'error'));
        }
        $this->content = $content;
    }

    /**
     * Returns $this->content.
     *
     * @return stdClass  $this->content.
     */
    public function get_content(): stdClass {
        return $this->content;
    }

    /**
     * Returns $this->content->contenttype.
     *
     * @return string  $this->content->contenttype.
     */
    public function get_content_type(): string {
        return $this->content->contenttype;
    }

    /**
     * Updates content_bank table with information in $this->content.
     *
     * @return boolean  True if the content has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     */
    public function update_content(): bool {
        global $USER, $DB;

        // A record with the id must exist in 'contenbank_content' table.
        // To improve performance, we are only checking the id is set, but no querying the database.
        if (!isset($this->content->id)) {
            throw new coding_exception(get_string('invalidcontentid', 'error'));
        }
        $this->content->usermodified = $USER->id;
        $this->content->timemodified = time();
        return $DB->update_record('contentbank_content', $this->content);
    }

    /**
     * Returns the name of the content.
     *
     * @return string   The name of the content.
     */
    public function get_name(): string {
        return $this->content->name;
    }

    /**
     * Returns the content ID.
     *
     * @return int   The content ID.
     */
    public function get_id(): int {
        return $this->content->id;
    }

    /**
     * Change the content instanceid value.
     *
     * @param int $instanceid    New instanceid for this content
     * @return boolean           True if the instanceid has been succesfully updated. False otherwise.
     */
    public function set_instanceid(int $instanceid): bool {
        $this->content->instanceid = $instanceid;
        return $this->update_content();
    }

    /**
     * Returns the $instanceid of this content.
     *
     * @return int   contentbank instanceid
     */
    public function get_instanceid(): int {
        return $this->content->instanceid;
    }

    /**
     * Change the content config values.
     *
     * @param string $configdata    New config information for this content
     * @return boolean              True if the configdata has been succesfully updated. False otherwise.
     */
    public function set_configdata(string $configdata): bool {
        $this->content->configdata = $configdata;
        return $this->update_content();
    }

    /**
     * Return the content config values.
     *
     * @return mixed   Config information for this content (json decoded)
     */
    public function get_configdata() {
        return $this->content->configdata;
    }

    /**
     * Returns the $file related to this content.
     *
     * @return stored_file  File stored in content bank area related to the given itemid.
     * @throws \coding_exception if not loaded.
     */
    public function get_file(): ?stored_file {
        $itemid = $this->get_id();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->content->contextid,
            'contentbank',
            'public',
            $itemid,
            'itemid, filepath, filename',
            false
        );
        if (!empty($files)) {
            $file = reset($files);
            return $file;
        }
        return null;
    }

    /**
     * Returns the file url related to this content.
     *
     * @return string       URL of the file stored in content bank area related to the given itemid.
     * @throws \coding_exception if not loaded.
     */
    public function get_file_url(): string {
        if (!$file = $this->get_file()) {
            return '';
        }
        $fileurl = moodle_url::make_pluginfile_url(
            $this->content->contextid,
            'contentbank',
            'public',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        return $fileurl;
    }

    /**
     * Returns user has access permission for the content itself (based on what plugin needs).
     *
     * @return bool     True if content could be accessed. False otherwise.
     */
    public function can_view(): bool {
        // There's no capability at content level to check,
        // but plugins can overwrite this method in case they want to check something related to content properties.
        return true;
    }
}
