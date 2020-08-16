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
 * Unit tests for core_table\external\fetch;
 *
 * @package   core_table
 * @category  test
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

declare(strict_types = 1);

namespace core_table\external\dynamic;

use core_table\local\filter\filter;
use advanced_testcase;

/**
 * Unit tests for core_table\external\fetch;
 *
 * @package   core_table
 * @category  test
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_test extends advanced_testcase {

    /**
     * Setup before class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->libdir}/externallib.php");
    }

    /**
     * Test execute invalid handler.
     */
    public function test_execute_invalid_handler(): void {
        $this->resetAfterTest();

        $this->expectException('UnexpectedValueException');
        $this->expectExceptionMessage("Unknown table handler, or table handler does not support dynamic updating.");

        // Tests that invalid users_participants_table class gets an exception.
        fetch::execute("core_user\users_participants_table", "", "email", "4", [], "1");
    }

    /**
     * Test execute invalid filter.
     */
    public function test_execute_invalid_filter(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Filter with an invalid name.
        $filter = [
            [
                'fullname' => 'courseid',
                'jointype' => filter::JOINTYPE_ANY,
                'values' => [(int)$course->id]
            ]
        ];
        $this->expectException('invalid_parameter_exception');
        $this->expectExceptionMessage("Invalid parameter value detected (filters => Invalid parameter value detected " .
        "(Missing required key in single structure: name): Missing required key in single structure: name");

        fetch::execute("core_user\participants_table",
            "user-index-participants-{$course->id}", "firstname", "4", $filter, (string)filter::JOINTYPE_ANY);

    }

    /**
     * Test execute fetch table.
     */
    public function test_execute_fetch_table(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 's1@moodle.com']);
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 's2@moodle.com']);
        $user3 = $this->getDataGenerator()->create_user(['email' => 's3@moodle.com']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 't1@moodle.com']);

        $this->setUser($teacher);

        $filter = [
            [
                'name' => 'courseid',
                'jointype' => filter::JOINTYPE_ANY,
                'values' => [(int)$course->id]
            ]
        ];

        $participantstable = fetch::execute("core_user\participants_table",
            "user-index-participants-{$course->id}", "firstname", "4", $filter, (string)filter::JOINTYPE_ANY);
        $html = $participantstable['html'];

        $this->assertStringContainsString($user1->email, $html);
        $this->assertStringContainsString($user2->email, $html);
        $this->assertStringContainsString($teacher->email, $html);
        $this->assertStringNotContainsString($user3->email, $html);
    }
}
