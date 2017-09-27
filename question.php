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

    public $twigset;// =  new SplObjectStorage();
    public $requires_scenario;
    public $requires_student;
    public $student;
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
        return array('answer' => PARAM_RAW);
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
                strlen($response['answer']) >= constants::FUNC_MIN_LENGTH;
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
           global $USER;
           $this->student = new qtype_coderunner_student($USER);
           parent::start_attempt($step,$variant);
           $this->twigset =    array();
           $this->capture_twig_variables();
           $this->logit($this->twigset);
           $this->initScenario("");     
           if($this->requires_scenario && isset($this->scenario) && count($this->scenario->data)>0 ) {
             $step->set_qt_var("_crs", $this->scenario->get_json_encoded());
           }
           if($this->requires_student  ) {
             $step->set_qt_var("_student", json_encode( $this->student ));
           }
           $this->logit($this);
    }
    
    public function initScenario($json){
        $js=$json;

	//need to iterate testecases,template and question text for twig variables


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
         $original_scenario->STUDENT=$this->student;
         $s = new stdClass();
         $s->json=$original_scenario->get_json_encoded();
 
         $cmd = $this->render_using_twig_with_params_forced($code,array('SCENARIO' => $s));        
         //NB php_task.php in jobe needs modifying to have more memory and not enforce --no-php.ini
         $jobe_answer = $this->jobe->execute($cmd, $lang, '');
         $this->scenario = new qtype_coderunner_scenario((isset($jobe_answer->output)?$jobe_answer->output:''));
        } else {
          $this->scenario = new qtype_coderunner_scenario('');
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
        global $USER;
        parent::apply_attempt_state($step);
        $sj=$step->get_qt_var("_crs");
        if (!is_null($sj)){
          $this->scenario=new qtype_coderunner_scenario($sj);
        } else if (!isset($this->scenario)){
	 $this->scenario=new qtype_coderunner_scenario(''); 
	}
        $sj=$step->get_qt_var("_student");
        if (!is_null($sj)){// overwrite the one we already have
          $this->student=json_decode($sj);
        } else if (!isset($this->student)){
	  $this->student = new qtype_coderunner_student($USER); 
        }
    }


    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        if (array_key_exists('answer', $response)) {
            if (empty($response['answer'])) {
                return get_string('answerrequired', 'qtype_coderunner');
            } elseif (strlen($response['answer']) < constants::FUNC_MIN_LENGTH) {
                return get_string('answertooshort', 'qtype_coderunner');
            }
        }
        if (array_key_exists('_testoutcome', $response)) {
            $outcome = unserialize($response['_testoutcome']);
            return $outcome->errormessage;
        } else {
            return get_string('unknownerror', 'qtype_coderunner');
        }
    }


    /** This function is used by the question engine to prevent regrading of
     *  unchanged submissions. This has been disabled (it always returns false)
     *  to avoid confusion by authors and students when changing templates
     *  or other question data. It seems that this is more of a problem for
     *  CodeRunner than normal question types. The slight downside is that
     *  students pay a penalty for submitting the same code twice.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse) {

        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
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


public function build_twig_set($template){
    preg_match_all('/\{\%\s*([^\%\}]*)\s*\%\}|\{\{\s*([^\}\}]*)\s*\}\}/i', $template, $matches);
    foreach($matches[2] as $v){
       $this->twigset[trim($v)]=null;
    }
}


public function capture_twig_variables(){
 $usetwig = ($this->usetwig == 1);
        if ($usetwig){
         //apply the template params to the student code
         if (isset($this->question->ismodelanswer) ){//NB ismodelanswer is set by edit_coderunner.php to indicate model answer
           try { $code     =   $question->render_using_twig_with_params($code,$this->templateparams);} catch (Exception $ee) {}
         }
         foreach ($this->testcases as $testcase) {
            //capture Twig variables for the testcase
                 $this->build_twig_set($testcase->testcode);
                 $this->build_twig_set($testcase->stdin);
                 $this->build_twig_set($testcase->expected);
                 $this->build_twig_set($testcase->extra);
            }
        }
       $this->build_twig_set($this->answer);
       $this->build_twig_set($this->answerpreload);
       $this->build_twig_set($this->template);
       $this->build_twig_set($this->scenariogenerator);
       if (isset($this->combinatortemplate)) {$this->build_twig_set($this->combinatortemplate);}
       if (isset($this->pertesttemplate)) {$this->build_twig_set($this->pertesttemplate);}
       $this->build_twig_set($this->questiontext);
       /*
  'STUDENT.username' => NULL,
  'SCENARIO.now' => NULL,
  'SCENARIO.a' => NULL,
  'SCENARIO.mon' => NULL,
  'QUESTION.id' => NULL,
  'THIS' => NULL,
  'STUDENT_ANSWER' => NULL,
  'QUESTION.usetwig' => NULL,
  '' => NULL,
  'testCase.testcode' => NULL,
  'SCENARIO.json | e(\'c\')' => NULL,
*/
  $rm = array();
  $this->requires_scenario=false;
  $this->requires_student=false;
  foreach($this->twigset as $key => $val){
    $query="STUDENT.";
    if (substr($key, 0, strlen($query)) === $query){
      $this->requires_student=true;
    }
    $query="SCENARIO.";
    if (substr($key, 0, strlen($query)) === $query){
      $this->requires_scenario=true;
    }
    $query="QUESTION.";
    if (substr($key, 0, strlen($query)) === $query){
      $rm[$key] = $val;
    }
    $query="testCase.";
    if (substr($key, 0, strlen($query)) === $query){
      $rm[$key] = $val;
    }
    $query="THIS";
    if (substr($key, 0, strlen($query)) === $query){
      $rm[$key] = $val;
    }
    $query="STUDENT_ANSWER";
    if (substr($key, 0, strlen($query)) === $query){
      $rm[$key] = $val;
    }
    if ($key === '') { $rm[$key]=$val;}
    $query="SCENARIO.json";
    if (substr($key, 0, strlen($query)) === $query){
      $rm[$key] = $val;
    }
  }
       $this->twigset= array_diff_key($this->twigset, $rm);
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
            'STUDENT' => $this->student,
            'SCENARIO' => $this->scenario->data
            );
          $result = $this->render_using_twig_with_params($result, $templateparams);
        }
      return $result;
     }

    /**
     * Grade the given student's response.
     * This implementation assumes a modified behaviour that will accept a
     * third array element in its response, containing data to be cached and
     * served up again in the response on subsequent calls.
     * @param array $response the qt_data for the current pending step. The
     * two relevant keys are '_testoutcome', which is a cached copy of the
     * grading outcome if this response has already been graded and 'answer'
     * (the student's answer) otherwise.
     * @param bool $isprecheck true iff this grading is occurring because the
     * student clicked the precheck button
     * @return 3-element array of the mark (0 - 1), the question_state (
     * gradedright, gradedwrong, gradedpartial, invalid) and the full
     * qtype_coderunner_testing_outcome object to be cached. The invalid
     * state is used when a sandbox error occurs.
     * @throws coding_exception
     */
    public function grade_response(array $response, $isprecheck=false) {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        $gradingreqd = true;
        if (!empty($response['_testoutcome'])) {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
            if ($testoutcome instanceof qtype_coderunner_testing_outcome  // Ignore legacy-format outcomes
                    && $testoutcome->isprecheck == $isprecheck) {
                $gradingreqd = false;  // Already graded and with same precheck state
            }
        }
        if ($gradingreqd) {
            // We haven't already graded this submission or we graded it with
            // a different precheck setting
            $code = $response['answer'];
            $testcases = $this->filter_testcases($isprecheck, $this->precheck);
            $runner = new qtype_coderunner_jobrunner();
            $testoutcome = $runner->run_tests($this, $code, $testcases, $isprecheck);
            $testoutcomeserial = serialize($testoutcome);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->run_failed()) {
            return array(0, question_state::$invalid, $datatocache);
        } else if ($testoutcome->all_correct()) {
             return array(1, question_state::$gradedright, $datatocache);
        } else if ($this->allornothing &&
                !($this->grader === 'TemplateGrader' && $this->iscombinatortemplate)) {
            return array(0, question_state::$gradedwrong, $datatocache);
        } else {
            // Allow partial marks if not allornothing or if it's a combinator template grader
            return array($testoutcome->mark_as_fraction(),
                    question_state::$gradedpartial, $datatocache);
        }
    }


    /**
     * @return an array of result column specifiers, each being a 2-element
     *  array of a column header and the testcase field to be displayed
     */
    public function result_columns() {
        if (isset($this->resultcolumns) && $this->resultcolumns) {
            $resultcolumns = json_decode($this->resultcolumns);
        } else {
            // Use default column headers, equivalent to json_decode of (in English):
            // '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]'.
            $resultcolumns = array(
                array(get_string('testcolhdr', 'qtype_coderunner'), 'testcode'),
                array(get_string('inputcolhdr', 'qtype_coderunner'), 'stdin'),
                array(get_string('expectedcolhdr', 'qtype_coderunner'), 'expected'),
                array(get_string('gotcolhdr', 'qtype_coderunner'), 'got'),
            );
        }
        return $resultcolumns;
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



    /* ================================================================
     * Interface methods for use by jobrunner.
       ================================================================*/

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


    // Return whether or not multiple stdins are allowed when using combiantor
    public function allow_multiple_stdins() {
        return $this->allowmultiplestdins;
    }

    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox() {
        global $CFG;
        $sandbox = $this->sandbox; // Get the specified sandbox (if question has one).
        if ($sandbox === null) {   // No sandbox specified. Use best we can find.
            $sandboxinstance = qtype_coderunner_sandbox::get_best_sandbox($this->language);
            if ($sandboxinstance === null) {
                throw new qtype_coderunner_exception("Language {$this->language} is not available on this system");
            }
        } else {
            $sandboxinstance = qtype_coderunner_sandbox::get_instance($sandbox);
            if ($sandboxinstance === null) {
                throw new qtype_coderunner_exception("Question is configured to use a non-existent or disabled sandbox ($sandbox)");
            }
        }

        return $sandboxinstance;
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
        global $DB, $USER;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = array();

        if (isset($question->filemanagerdraftid)) {
            // If we're just validating a question, get files from user draft area
            $draftid = $question->filemanagerdraftid;
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
        } else {
            // Otherwise, get the stored files for this question
            $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $questionid);
        }

        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $filemap[$f->get_filename()] = $f->get_content();
            }
        }
        return $filemap;
    }


    public function logit($var){
//	$x=var_export($var,true);
//        $x = $x . "\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n\n";
//        file_put_contents("/var/www/moodledata/aa.log", $x, FILE_APPEND | LOCK_EX);
    }
}
