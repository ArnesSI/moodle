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
 * Events tests.
 *
 * @package    assignsubmission_comments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_comments\event;

use mod_assign_test_generator;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/comment/lib.php');

/**
 * Notifications tests class.
 *
 * @package    assignsubmission_comments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifications_test extends \advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Test notification message, sent to student, when a teacher creates a new comment.
     */
    public function test_comment_created_by_teacher_notification(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance($course);

        $this->setUser($teacher);
        $submission = $assign->get_user_submission($student->id, true);

        $context = $assign->get_context();
        $options = new \stdClass();
        $options->area = 'submission_comments';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_comments';
        $options->showcount = true;
        $options->displaycancel = true;

        $comment = new \comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectMessages();
        $comment->add('New comment');
        $notifications = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $notifications);

        // Checking that the notification contains the expected values.
        $this->assertEquals($teacher->id, $notifications[0]->useridfrom);
        $this->assertEquals($student->id, $notifications[0]->useridto);
        $this->assertEquals(get_string('gradercommentupdatedsmall', 'assign', ['username' => $teacher->firstname . ' ' . $teacher->lastname, 'assignment' => $assign->get_course_module()->name]), $notifications[0]->subject);

        $url = new \moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $notifications[0]->contexturl);
    }

    /**
     * Test notification message, to teacher, when a student creates a new comment.
     */
    public function test_comment_created_by_student_notification(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance($course, ['sendnotifications' => true]);

        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);

        $context = $assign->get_context();
        $options = new \stdClass();
        $options->area = 'submission_comments';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_comments';
        $options->showcount = true;
        $options->displaycancel = true;

        $comment = new \comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectMessages();
        $comment->add('New comment');
        $notifications = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $notifications);

        // Checking that the notification contains the expected values.
        $this->assertEquals($student->id, $notifications[0]->useridfrom);
        $this->assertEquals($teacher->id, $notifications[0]->useridto);
        $this->assertEquals(get_string('studentcommentupdatedsmall', 'assign', ['username' => $student->firstname . ' ' . $student->lastname, 'assignment' => $assign->get_course_module()->name]), $notifications[0]->subject);

        $url = new \moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $notifications[0]->contexturl);
    }
}
