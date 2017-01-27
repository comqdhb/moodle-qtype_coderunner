<?php
// This file is part of CodeRunner - http://coderunner.org.nz
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script provides a class with support methods for running question tests in bulk.
 * It is taken from the qtype_stack plugin with slight modifications.
 *
 * @package   qtype_coderunner
 * @copyright 2016 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_bulk_tester  {

    const PASS = 0;
    const MISSINGANSWER = 1;
    const FAIL = 2;
    const EXCEPTION = 3;

    /**
     * Get all the contexts that contain at least one CodeRunner question, with a
     * count of the number of those questions.
     *
     * @return array context id => number of CodeRunner questions.
     */
    public function get_coderunner_questions_by_context() {
        global $DB;

        return $DB->get_records_sql_menu("
            SELECT ctx.id, COUNT(q.id) AS numcoderunnerquestions
              FROM {context} ctx
              JOIN {question_categories} qc ON qc.contextid = ctx.id
              JOIN {question} q ON q.category = qc.id
             WHERE q.qtype = 'coderunner'
          GROUP BY ctx.id, ctx.path
          ORDER BY ctx.path
        ");
    }

    /**
     * Run the sample answer for all questions belonging to
     * a given context that have a sample answer.
     *
     * Do output as we go along.
     *
     * @param context $context the context to run the tests for.
     * @return array with three elements:
     *              int a count of how many tests passed
     *              array of messages relating to the questions with failures
     *              array of messages relating to the questions without sample answers
     */
    public function run_all_tests_for_context(context $context) {
        global $DB, $OUTPUT;

        // Load the necessary data.
        $categories = get_categories_for_contexts($context->id);
        $questiontestsurl = new moodle_url('/question/type/coderunner/questiontestrun.php');
        if ($context->contextlevel == CONTEXT_COURSE) {
            $questiontestsurl->param('courseid', $context->instanceid);
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $questiontestsurl->param('cmid', $context->instanceid);
        } else {
            $questiontestsurl->param('courseid', SITEID);
        }
        $numpasses = 0;
        $failingtests = array();
        $missinganswers = array();

        foreach ($categories as $category) {
            $questionids = $DB->get_records_menu('question',
                    array('category' => $category->id, 'qtype' => 'coderunner'), 'name', 'id,name');
            if (!$questionids) {
                continue;
            }

            $numquestions = count($questionids);
            echo $OUTPUT->heading("{$category->name} ($numquestions)", 4);
            echo "<ul>\n";
            foreach ($questionids as $questionid => $name) {
                $questionname = format_string($name);
                $previewurl = new moodle_url($questiontestsurl, array('questionid' => $questionid));
                $questionnamelink = html_writer::link($previewurl, $questionname, array('target' => '_blank'));
                list($outcome, $message) = $this->load_and_test_question($questionid);
                $html = "<li>$questionnamelink: $message</li>";
                if ($outcome === self::PASS) {
                    $numpasses += 1;
                } else if ($outcome === self::MISSINGANSWER) {
                    $missinganswers[] = $questionnamelink;
                } else {
                    $failingtests[] = "$questionnamelink: $message";
                }
                $html = "<li>$questionnamelink: $message";
                echo $html;
                flush(); // Force output to prevent timeouts and show progress.
            }
            echo "</ul>\n";
        }

        return array($numpasses, $failingtests, $missinganswers);
    }


    /**
     * Load and test a specified question.
     * @param int $questionid the id of the question to be tested
     * @return array with 2 elements: the status (one of pass, fail, missinganswer
     *  or exception) and a string message describing the outcome.
     */
    private function load_and_test_question($questionid) {
        try {
            $question = question_bank::load_question($questionid);
            if (empty(trim($question->answer))) {
                $message = 'No sample answer.';
                $status = self::MISSINGANSWER;
            } else {
                $question->ismodelanswer = true;
                $ok = $this->test_question($question);
                if ($ok) {
                    $message = get_string('pass', 'qtype_coderunner');
                    $status = self::PASS;
                } else {
                    $message = get_string('fail', 'qtype_coderunner');
                    $status = self::FAIL;
                }
            }
        } catch (qtype_coderunner_exception $e) {
            if (isset($question)) {
                $questionname = format_string($question->name);
            } else {
                $questionname = 'Unknown question';
            }
            $message = '**** ' . get_string('questionloaderror', 'qtype_coderunner') .
                    ' ' . $questionname . ': ' . $e->getMessage() . ' ****';
            $status = self::EXCEPTION;
        }
        return array($status, $message);
    }

    /**
     * Run the sample answer for the given question (if there is one).
     *
     * @param qtype_coderunner_question $question the question to test.
     * @return bool true if the sample answer passed, else false.
     */
    private function test_question($question) {
        core_php_time_limit::raise(60); // Prevent PHP timeouts.
        gc_collect_cycles(); // Because PHP's default memory management is rubbish.
        $answer = $question->answer;
        try {
            list($fraction, $state) = $question->grade_response(array('answer' => $answer), false);
            $ok = $state == question_state::$gradedright;
        } catch (qtype_coderunner_exception $e) {
            $ok = false; // If user clicks link to see why, they'll get the same exception.
        }
        return $ok;
    }

    /**
     * Print an overall summary, with a link back to the bulk test index.
     *
     * @param int $numpasses count of tests passed.
     * @param array $failingtests list of the ones that failed.
     * @param array $missinganswers list of all the ones without sample answers.
     */
    public function print_overall_result($numpasses, $failingtests, $missinganswers) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('overallresult', 'qtype_coderunner'), 2);
        echo html_writer::tag('p', $numpasses . ' ' . get_string('passes', 'qtype_coderunner'));
        echo html_writer::tag('p', count($failingtests) . ' ' . get_string('fails', 'qtype_coderunner'));
        echo html_writer::tag('p', count($missinganswers) . ' ' . get_string('missinganswers', 'qtype_coderunner'));

        if (count($failingtests) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_failures', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($failingtests as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }

        if (count($missinganswers) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_noanswer', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($missinganswers as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }

        echo html_writer::tag('p', html_writer::link(new moodle_url('/question/type/coderunner/bulktestindex.php'),
                get_string('back')));
    }
}
