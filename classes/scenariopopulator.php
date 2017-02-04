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

/*
 * A scenario_populator takes  a scenario and 
 * applies a script/code to it filling in:
 *      the variables that the populator provides
 *      the variables that the populator needs in order to provide changes
 *      the new variables in the data object which can then be used in twiggin.
 * 
 * NB the code should have the following twig: SCENARIO.json
 * which will be substituded into the question designers code when a question attempt is being constructed
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2017, David Bowes, University of Hertfordshire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_coderunner_scenariopopulator {

    public $code;
    public $lang;
    private $jobe;

    /**
     * 
     * @param string $json
     */
    function __construct($code, $lang) {
        $this->code = $code;
        $this->lang = $lang;
    }

    public function get_updated_scenario($original_scenario) {
        if (!isset($this->jobe)) {
            $this->jobe = new qtype_coderunner_jobesandbox();
        }
        /*
          $this->logit("l=" . $this->lang);
          $this->logit("cmd=" . $this->code);
          $this->logit("orig= " . $original_scenario->get_json_encoded());
         */
        $cmd = str_replace('{{ SCENARIO.json }}', $original_scenario->get_json_encoded(), $this->code);
        //$this->logit("cmd' ". $cmd);
        //$cmd='echo \'' . $sj . '\'  | sed "s/\"data\":{/\"data\":{\"now\":\"$(date)\",/g"';
        //$lang='sh';
        $j = $this->jobe->execute($cmd, $this->lang, '');
        $this->logit(">>>> " . $cmd . " " . $this->lang . " " . var_export($j,true));
        $new_scenario = new qtype_coderunner_scenario((isset($j->output)?$j->output:''));
        return $new_scenario;
    }

    private function logit($txt) {
        global $USER;
        $file = '/var/www/moodledata/ablog.txt';
        $person = $txt . "\n";
        file_put_contents($file, $person, FILE_APPEND | LOCK_EX);
    }

}

