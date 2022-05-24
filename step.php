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
 * Trigger dataflow settings.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;
use tool_dataflows\step;
use tool_dataflows\step_form;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

// The dataflow id, if not provided, it is as if the user is creating a new dataflow.
$dataflowid = required_param('dataflowid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

require_login();

$overviewurl = new moodle_url('/admin/tool/dataflows/overview.php');
$dataflowstepsurl = new moodle_url('/admin/tool/dataflows/steps.php', ['dataflowid' => $dataflowid]);
$url = new moodle_url('/admin/tool/dataflows/step.php', ['id' => $id, 'dataflowid' => $dataflowid]);
$context = context_system::instance();

// Check capabilities and setup page.
require_capability('tool/dataflows:managedataflows', $context);

// Ensure the dataflow exists before continuing (you should not be able to create a step without a dataflow).
try {
    $dataflow = new dataflow($dataflowid);
} catch (\Exception $e) {
    \core\notification::error(get_string('dataflowrequiredforstepcreation', 'tool_dataflows'));
    // We are done, so let's redirect somewhere.
    redirect($overviewurl);
}


// Set the PAGE URL (and mandatory context). Note the ID being recorded, this is important.
$PAGE->set_context($context);
$PAGE->set_url($url);

$persistent = null;
if (!empty($id)) {
    $persistent = new step($id);
    $dependencies = $persistent->dependencies();
}

// Render the specific dataflow form.
$customdata = [
    'persistent' => $persistent ?? null, // An instance, or null.
    'dataflowid' => $dataflowid, // Required as the step needs to be linked to a dataflow record.
    'userid' => $USER->id, // For the hidden userid field.
];
$form = new step_form($PAGE->url->out(false), $customdata);
if ($form->is_cancelled()) {
    redirect($dataflowstepsurl);
}

// Populate the foreign dependencies data if there was any.
if (!empty($dependencies)) {
    $form->set_data(['dependson' => array_column($dependencies, 'id')]);
}

if (($data = $form->get_data())) {
    try {
        if (empty($data->id)) {
            // If we don't have an ID, we know that we must create a new record.
            // Call your API to create a new persistent from this data.
            // Or, do the following if you don't want capability checks (discouraged).
            $persistent = new step(0, $data);
            $dependson = $data->dependson;
            unset($data->dependson);
            // Only unset field (as it should be set based on the name) if it is empty.
            if (empty($data->alias)) {
                unset($data->alias);
            }
            // Ensure the values are set through a loop which should ensure it goes through set_* methods.
            foreach ($data as $key => $value) {
                $persistent->$key = $value;
            }
            $persistent->depends_on($dependson);
            $persistent->upsert();
        } else {
            // We had an ID, this means that we are going to update a record.
            // Call your API to update the persistent from the data.
            // Or, do the following if you don't want capability checks (discouraged).
            $dependson = $data->dependson;
            unset($data->dependson);
            // Only unset field (as it should be set based on the name) if it is empty.
            if (empty($data->alias)) {
                unset($data->alias);
            }
            // Ensure the values are set through a loop which should ensure it goes through set_* methods.
            foreach ($data as $key => $value) {
                $persistent->$key = $value;
            }
            $persistent->depends_on($dependson);
            $persistent->upsert();
        }
        \core\notification::success(get_string('changessaved'));
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }

    // We are done, so let's redirect somewhere.
    redirect($dataflowstepsurl);
}

// Display the mandatory header and footer.
$heading = get_string('new_step', 'tool_dataflows');
if (isset($persistent)) {
    $heading = get_string('update_step', 'tool_dataflows');
}

$title = implode(': ', array_filter([
    get_string('pluginname', 'tool_dataflows'),
    $dataflow->name,
    $heading,
    $persistent->name ?? '',
]));
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'tool_dataflows'). ': ' .$dataflow->name);
echo $OUTPUT->header();

// Output headings.
echo $OUTPUT->heading($heading);

// And display the form, and its validation errors if there are any.
$form->display();

// Display footer.
echo $OUTPUT->footer();
