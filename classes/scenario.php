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

/* A scenario that allows the closure algorithm to build a scenario of 
 * data for a question and possible a series of connected questions
 * which may depend on the data provided by the current question
 * 
 * A scenario can determine if it can provide extra data based on a current scenario
 * 
 * A scenario_populato takes the json version of a scenario and 
 * applies a script/code to it filling in:
 *      the variables that the populator provides
 *      the variables that the populator needs in order to provide changes
 *      the new variables in the data object which can then be used in twiggin.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2017, David Bowes, University of Hertfordshire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_coderunner_scenario {

    public $data;
    public $provides = array();
    public $requires = array();
    public $err_message = null;

    /**
     * 
     * @param string $json
     */
    function __construct($json) {
        $a = json_decode($json);
        if ($a === null && json_last_error() !== JSON_ERROR_NONE) {
            $err_message = 'woops, your json was not well formed';
        } else {
            if (isset($a->data)) {
                $this->data = $a->data;
            } else {
                $this->data = new stdClass();
            }
            if (isset($a->requires)) {
                $this->requires = $a->requires;
            }
            if (isset($a->provides)) {
                $this->provides = $a->provides;
            }
        }
    }

    /**
     * 
     * @return string
     */
    public function get_json_encoded() {
        return json_encode($this);
    }

    /**
     * 
     * @return type
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * 
     * @param Scenario $scenario
     * @return boolean
     */
    public function can_provide_data_from_scenario(qtype_coderunner_scenario $scenario) {
        foreach ($this->requires as $required) {
            if (!$this->data_contains_variable($scenario->data, $required)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * @param type $data
     * @param type $required
     * @return boolean
     */
    public function data_contains_variable($data, $required) {
        $splitted_text = explode(".", $required);
        return $this->recursive_check_for_variable($data, $splitted_text);
    }

    /**
     * 
     * @param type $data
     * @param type $splitted_text
     * @return boolean
     */
    private function recursive_check_for_variable($data, $splitted_text) {
        if (count($splitted_text) == 0) {
            return true;
        }
        $tmp = $splitted_text[0];
        if (isset($data->$tmp)) {
            if (count($splitted_text) == 1) {
                return true;
            }
            return $this->recursive_check_for_variable($data->$tmp, array_slice($splitted_text, 1));
        } else {
            return false;
        }
    }

    /**
     * 
     * @param type $data
     * @param type $required
     * @return type
     */
    public function get_data_for_variable($variable_name) {
        $splitted_text = explode(".", $variable_name);
        return $this->recursive_get_for_variable($this->data, $splitted_text);
    }

    /**
     * 
     * @param type $data
     * @param type $splitted_text
     * @return boolean
     */
    private function recursive_get_for_variable($data, $splitted_text) {
        if (count($splitted_text) == 0) {
            return NULL;
        }
        $tmp = $splitted_text[0];
        if (isset($data->$tmp)) {
            $d = $data->$tmp;
            if (count($splitted_text) == 1) {
                return $d;
            }
            return $this->recursive_get_for_variable($d, array_slice($splitted_text, 1));
        } else {
            return NULL;
        }
    }

    /**
     * 
     * @param type $data
     * @param type $required
     * @return type
     */
    public function set_data_for_variable($variable_name, $val) {
        $splitted_text = explode(".", $variable_name);
        $this->recursive_set_for_variable($this->data, $splitted_text, $val);
    }

    /**
     * 
     * @param type $data
     * @param type $splitted_text
     */
    private function recursive_set_for_variable($data, $splitted_text, $val) {
        //var_dump($data);
        if (count($splitted_text) == 0) {
            return NULL;
        }
        $tmp = $splitted_text[0];
        if (count($splitted_text) == 1) {
            if (!isset($data->$tmp)) {
                $data->$tmp = $val;
            } else {
                $this->err_message = 'variable has already been set';
            }
            return;
        }

        if (!isset($data->$tmp)) {
            $data->$tmp = new stdClass();
        }
        $this->recursive_set_for_variable($data->$tmp, array_slice($splitted_text, 1), $val);
    }

    /**
     * 
     * @param stdClass $object
     * @param string $name
     */
    public function addObject(stdClass $object, $variable_name) {
        $this->data->$variable_name = $object;
    }

}

