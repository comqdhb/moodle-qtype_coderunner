<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
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
 * coderunner question definition classes.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . '/question/behaviour/adaptive/behaviour.php');
require_once($CFG->dirroot . '/question/engine/questionattemptstep.php');
require_once($CFG->dirroot . '/question/behaviour/adaptive_adapted_for_coderunner/behaviour.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

use qtype_coderunner\constants;

/**
 * Represents a 'CodeRunner' question.
 */
class qtype_coderunner_question extends question_graded_automatically {

    public $testcases; // Array of testcases.


    /**
     * Override default behaviour so that we can use a specialised behaviour
     * that caches test results returned by the call to grade_response().
     *
     * @param question_attempt $qa the attempt we are creating an behaviour for.
     * @param string $preferredbehaviour the requested type of behaviour.
     * @return question_behaviour the new behaviour object.
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        // Regardless of the preferred behaviour, always use an adaptive
        // behaviour.
        return  new qbehaviour_adaptive_adapted_for_coderunner($qa, $preferredbehaviour);
    }

    public function get_expected_data() {
        return array('answer' => PARAM_RAW, 'rating' => PARAM_INT);
    }


    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function is_gradable_response(array $response) {
        return array_key_exists('answer', $response) &&
                !empty($response['answer']) &&
                strlen($response['answer']) > constants::FUNC_MIN_LENGTH;
    }

    public function is_complete_response(array $response) {
        return $this->is_gradable_response($response);
    }

/**
     * INHERITED FROM questionbase.php
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step.
     *
     * This is where the question can do any initialisation required on a
     * per-attempt basis. For example, this is where the multiple choice
     * question type randomly shuffles the choices (if that option is set).
     *
     * Any information about how the question has been set up for this attempt
     * should be stored in the $step, by calling $step->set_qt_var(...).
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being started. Can be used to store state.
     * @param int $varant which variant of this question to start. Will be between
     *      1 and {@link get_num_variants()} inclusive.
     */
    public function start_attempt(question_attempt_step $step, $variant) {
           parent::start_attempt($step,$variant);
           $this->initScenario("");     
           if(isset($this->scenario) && count($this->scenario->data)>0 ) {
             $step->set_qt_var("_crs", $this->scenario->get_json_encoded());
           }
    }
    
    public function initScenario($json){
        global $USER;   
        $js=$json;

        $sj="{\"data\":{\"a\":\"b\"},\"provides\":[],\"requires\":[],\"err_message\":null}";
        $code='echo \'{{ SCENARIO.json }}\'  | sed "s/\"data\":{/\"data\":{\"now\":\"$(date)\",/g"';
        $lang='sh';

        $code=$this->scenariogenerator;
        $lang=$this->scenariotype;

        //NB only call jobe if code and lang exist
        if (isset($code) && isset($lang) && $code !== '' && $lang !== ''){

         if (!isset($this->jobe)) {
             $this->jobe = new qtype_coderunner_jobesandbox();
         }
         $original_scenario=new qtype_coderunner_scenario($sj);
         $original_scenario->STUDENT=new qtype_coderunner_student($USER);
         $s = new stdClass();
         $s->json=$original_scenario->get_json_encoded();
 
         $cmd = $this->render_using_twig_with_params_forced($code,array('SCENARIO' => $s));        
         //NB php_task.php in jobe needs modifying to have more memory and not enforce --no-php.ini
         $jobe_answer = $this->jobe->execute($cmd, $lang, '');
         $this->scenario = new qtype_coderunner_scenario((isset($jobe_answer->output)?$jobe_answer->output:''));
        } else {
          $this-scenario = new qtype_coderunner_scenario('');
        }
    }

    /**
     * INHERITED FROM questionbase.php
     * When an in-progress {@link question_attempt} is re-loaded from the
     * database, this method is called so that the question can re-initialise
     * its internal state as needed by this attempt.
     *
     * For example, the multiple choice question type needs to set the order
     * of the choices to the order that was set up when start_attempt was called
     * originally. All the information required to do this should be in the
     * $step object, which is the first step of the question_attempt being loaded.
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being loaded.
     */
    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        $sj=$step->get_qt_var("_crs");
        if (!is_null($sj)){
          $this->scenario=new qtype_coderunner_scenario($sj);
        }
    }


    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        return get_string('answerrequired', 'qtype_coderunner');
    }


    /** This function is used by the question engine to prevent regrading of
     *  unchanged submissions.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        if (!question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer')
            || !question_utils::arrays_same_at_key_integer(
                $prevresponse, $newresponse, 'rating')) {
            return false;
        }
        return true;
    }

    public function get_correct_response() {
        return $this->get_correct_answer();
    }


    public function get_correct_answer() {
        global $USER;
        // Return the sample answer, if supplied.
        return isset($this->answer) ? array('answer' => $this->render_using_twig($this->answer)) : array();
    }


private function load_twig(){
global $USER;
if (!isset($this->twig)){
          Twig_Autoloader::register();
          $loader = new Twig_Loader_String();
          $this->twig = new Twig_Environment($loader, array(
            'debug' => true,
            'autoescape' => false,
            'optimizations' => 0
            ));
          $twigcore = $this->twig->getExtension('core');
        $twigcore->setEscaper('py', 'qtype_coderunner_escapers::python');
        $twigcore->setEscaper('python', 'qtype_coderunner_escapers::python');
        $twigcore->setEscaper('c',  'qtype_coderunner_escapers::java');
        $twigcore->setEscaper('java', 'qtype_coderunner_escapers::java');
        $twigcore->setEscaper('ml', 'qtype_coderunner_escapers::matlab');
        $twigcore->setEscaper('matlab', 'qtype_coderunner_escapers::matlab');
           }
}


public function render_using_twig_with_params_forced($some_text,$params){
          $this->load_twig();
          return  $this->twig->render($some_text, $params);

}

public function render_using_twig_with_params($some_text,$params){

$result=$some_text;
$this->load_twig();
if (isset($this->usetwig) && $this->usetwig == 1)
        {
          $result = $this->render_using_twig_with_params_forced($result, $params);
        }
return $result;

}


public function render_using_twig($some_text){
     global $USER;
     $result=$some_text;
     if (isset($this->usetwig) && $this->usetwig == 1)
        {
          $templateparams = array(
            'IS_PRECHECK' =>  ($this->precheck?"1":"0"),
            'QUESTION' => $this,
            'STUDENT' => new qtype_coderunner_student($USER),
            'SCENARIO' => $this->scenario->data
            );
          $result = $this->render_using_twig_with_params($result, $templateparams);
        }
      return $result;
     }

    // Grade the given 'response'.
    // This implementation assumes a modified behaviour that will accept a
    // third array element in its response, containing data to be cached and
    // served up again in the response on subsequent calls.
    public function grade_response(array $response, $isprecheck=false) {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        if (empty($response['_testoutcome'])) {
            $code = $response['answer'];
            $testcases = $this->filter_testcases($isprecheck, $this->precheck);
            $runner = new qtype_coderunner_jobrunner();
            $testoutcome = $runner->run_tests($this, $code, $testcases, $isprecheck);
            $testoutcomeserial = serialize($testoutcome);
        } else {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->all_correct()) {
             return array(1, question_state::$gradedright, $datatocache);
        } else if ($this->allornothing && $this->grader !== 'TemplateGrader') {
            return array(0, question_state::$gradedwrong, $datatocache);
        } else {
            return array($testoutcome->mark_as_fraction(),
                    question_state::$gradedpartial, $datatocache);
        }
    }


    // Return an array of all the use_as_example testcases.
    public function example_testcases() {
        return array_filter($this->testcases, function($tc) {
                    return $tc->useasexample;
        });
    }


    // Extract and return the appropriate subset of the set of question testcases
    // given $isprecheckrun (true iff this was a run initiated by clicking
    // precheck) and the question's prechecksetting (0, 1, 2, 3, 4 for Disable,
    // Empty, Examples, Selected and All respectively).
    protected function filter_testcases($isprecheckrun, $prechecksetting) {
        if (!$isprecheckrun) {
            if ($prechecksetting != constants::PRECHECK_SELECTED) {
                return $this->testcases;
            } else {
                return $this->selected_testcases(false);
            }
        } else { // This is a precheck run.
            if ($prechecksetting == constants::PRECHECK_EMPTY) {
                return array($this->empty_testcase());
            } else if ($prechecksetting == constants::PRECHECK_EXAMPLES) {
                return $this->example_testcases();
            } else if ($prechecksetting == constants::PRECHECK_SELECTED) {
                return $this->selected_testcases(true);
            } else if ($prechecksetting == constants::PRECHECK_ALL) {
                return $this->testcases;
            } else {
                throw new coding_exception('Precheck clicked but no precheck button?!');
            }
        }
    }


    // Return the appropriate subset of questions in the case that the question
    // precheck setting is "selected", given whether or not this is a precheckrun.
    protected function selected_testcases($isprecheckrun) {
        $testcases = array();
        foreach ($this->testcases as $testcase) {
            if (($isprecheckrun && $testcase->testtype != constants::TESTTYPE_NORMAL) ||
                (!$isprecheckrun && $testcase->testtype != constants::TESTTYPE_PRECHECK)) {
                $testcases[] = $testcase;
            }
        }
        return $testcases;
    }


    // Return an empty testcase - an artifical testcase with all fields
    // empty or zero except for a mark of 1.
    private function empty_testcase() {
        return (object) array(
            'testtype' => 0,
            'testcode' => '',
            'stdin'    => '',
            'expected' => '',
            'extra'    => '',
            'display'  => 0,
            'useasexample' => 0,
            'hiderestiffail' => 0,
            'mark'     => 1
        );
    }



    /******************************************************************.
     * Interface methods for use by jobrunner.
     ******************************************************************/
    // Return the template.
    public function get_template() {
        return $this->template;
    }


    // Return the programming language used to run the code.
    public function get_language() {
        return $this->language;
    }

    // Get the showsource boolean.
    public function get_show_source() {
        return $this->showsource;
    }


    // Return the regular expression used to split the combinator template
    // output into individual tests.
    public function get_test_splitter_re() {
        return $this->testsplitterre;
    }


    // Return whether or not the template is a combinator.
    public function get_is_combinator() {
        return $this->iscombinatortemplate;
    }

    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox() {
        global $CFG;
        $sandbox = $this->sandbox; // Get the specified sandbox (if question has one).
        if ($sandbox === null) {   // No sandbox specified. Use best we can find.
            $sandbox = qtype_coderunner_sandbox::get_best_sandbox($this->language);
            if ($sandbox === null) {
                throw new qtype_coderunner_exception("Language {$this->language} is not available on this system");
            }
        } else {
            if (!get_config('qtype_coderunner', strtolower($sandbox) . '_enabled')) {
                throw new qtype_coderunner_exception("Question is configured to use a disabled sandbox ($sandbox)");
            }
        }

        return qtype_coderunner_sandbox::make_sandbox($sandbox);
    }


    // Get an instance of the grader to be used to grade this question.
    public function get_grader() {
        global $CFG;
        $grader = $this->grader == null ? constants::DEFAULT_GRADER : $this->grader;
        if ($grader === 'CombinatorTemplateGrader') { // Legacy grader type.
            $grader = 'TemplateGrader';
            assert($this->iscombinatortemplate);
        }
        $graders = qtype_coderunner_grader::available_graders();
        $graderclass = $graders[$grader];

        $graderinstance = new $graderclass();
        return $graderinstance;
    }


    // Return all the datafiles to use for a run, namely all the files
    // uploaded with this question itself plus all the files uploaded with the
    // prototype.
    public function get_files() {
        if ($this->prototypetype != 0) { // Is this a prototype question?
            $files = array(); // Don't load the files twice.
        } else {
            // Load any files from the prototype.
            $context = qtype_coderunner::question_context($this);
            $prototype = qtype_coderunner::get_prototype($this->coderunnertype, $context);
            $files = $this->get_data_files($prototype, $prototype->questionid);
        }
        $files += $this->get_data_files($this, $this->id);  // Add in files for this question.
        return $files;
    }


    // Get the sandbox parameters for a run.
    public function get_sandbox_params() {
        if (isset($this->sandboxparams)) {
            $sandboxparams = json_decode($this->sandboxparams, true);
        } else {
            $sandboxparams = array();
        }

        if (isset($this->cputimelimitsecs)) {
            $sandboxparams['cputime'] = intval($this->cputimelimitsecs);
        }
        if (isset($this->memlimitmb)) {
            $sandboxparams['memorylimit'] = intval($this->memlimitmb);
        }
        if (isset($this->templateparams) && $this->templateparams != '') {
            $this->parameters = json_decode($this->templateparams);
        }
        return $sandboxparams;
    }


    /**
     *  Return an associative array mapping filename to datafile contents
     *  for all the datafiles associated with a given question (which may
     *  be a real question or, in the case of a prototype, the question_options
     *  row) and the questionid from the mdl_questions table.
     */
    private static function get_data_files($question, $questionid) {
        global $DB;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = array();
        $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $questionid);

        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $filemap[$f->get_filename()] = $f->get_content();
            }
        }
        return $filemap;
    }
}
