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
 * Progress Section Format
 *
 * @package    course/format
 * @subpackage vsf
 * @version    See the value of '$plugin->version' in version.php.
 * @copyright  &copy; 2016-onwards G J Barnard in respect to modifications of standard topics format.
 * @author     G J Barnard - gjbarnard at gmail dot com and {@link http://moodle.org/user/profile.php?id=442195}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

class format_vsf extends core_courseformat\base {
    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    public function uses_course_index() {

        // Dirty tweak to allow both index and submenu.
        // First run will be -> course_index_drawer().
        // Next run will be -> activity_navigation().

        static $i = 0;
        $i++;

        if ($i === 1) {
            return true;
        }

        return false;
    }

    public function uses_indentation(): bool {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Section #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Section 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else if ($section->section == 0) {
            return get_string('section0name', 'format_vsf');
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the topics course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_vsf');
        } else {
            // Use format_base::get_default_section_name implementation which will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array('search_forums', 'news_items', 'calendar_upcoming', 'recent_activity')
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Progress section format uses the following options:
     * - numsections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            /* Note: Because 'admin_setting_configcolourpicker' in 'settings.php' needs to use a prefixing '#'
                     this needs to be stripped off here if it's there for the format's specific colour picker. */
            $defaults = $this->get_course_format_colour_defaults();

            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => 1, // Completely invisible.
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay ?? COURSE_DISPLAY_SINGLEPAGE,
                    'type' => PARAM_INT
                ),
                'chart' => array(
                    'default' => get_config('format_vsf', 'defaultchart'),
                    'type' => PARAM_INT
                ),
                'moduleviewbutton' => array(
                    'default' => get_config('format_vsf', 'defaultmoduleviewbutton'),
                    'type' => PARAM_INT
                ),
                // Continue button.
                'continuebackgroundcolour' => array(
                    'default' => $defaults['defaultcontinuebackgroundcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                'continuetextcolour' => array(
                    'default' => $defaults['defaultcontinuetextcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                // Section header.
                'sectionheaderbackgroundcolour' => array(
                    'default' => $defaults['defaultsectionheaderbackgroundcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                'sectionheaderbackgroundhvrcolour' => array(
                    'default' => $defaults['defaultsectionheaderbackgroundhvrcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                'sectionheaderforegroundcolour' => array(
                    'default' => $defaults['defaultsectionheaderforegroundcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                'sectionheaderforegroundhvrcolour' => array(
                    'default' => $defaults['defaultsectionheaderforegroundhvrcolour'],
                    'type' => PARAM_ALPHANUM
                ),
                // Columns.
                'layoutcolumns' => array(
                    'default' => get_config('format_vsf', 'defaultlayoutcolumns'),
                    'type' => PARAM_INT,
                )
            );
        }
        if ($foreditform && !isset($courseformatoptions['numsections']['label'])) {
            if (empty($defaults)) {
                /* Note: Because 'admin_setting_configcolourpicker' in 'settings.php' needs to use a prefixing '#'
                         this needs to be stripped off here if it's there for the format's specific colour picker. */
                $defaults = $this->get_course_format_colour_defaults();
            }

            $courseconfig = get_config('moodlecourse');
            $max = $courseconfig->maxsections;
            if (!isset($max) || !is_numeric($max)) {
                $max = 52;
            }
            $sectionmenu = array();
            for ($i = 0; $i <= $max; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'element_type' => 'hidden'
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'chart' => array(
                    'label' => new lang_string('chart', 'format_vsf'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            1 => new lang_string('none'),                    // None.
                            2 => new lang_string('barchart', 'format_vsf'),  // Bar.
                            3 => new lang_string('donutchart', 'format_vsf') // Donut.
                        )
                    ),
                    'help' => 'chart',
                    'help_component' => 'format_vsf',
                ),
                'moduleviewbutton' => array(
                    'label' => new lang_string('moduleviewbutton', 'format_vsf'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            1 => new lang_string('no'),
                            2 => new lang_string('yes')
                        )
                    ),
                    'help' => 'moduleviewbutton',
                    'help_component' => 'format_vsf',
                ),
                // Continue button.
                'continuebackgroundcolour' => array(
                    'label' => new lang_string('continuebackgroundcolour', 'format_vsf'),
                    'help' => 'continuebackgroundcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultcontinuebackgroundcolour'])
                    )
                ),
                'continuetextcolour' => array(
                    'label' => new lang_string('continuetextcolour', 'format_vsf'),
                    'help' => 'continuetextcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultcontinuetextcolour'])
                    )
                ),
                // Section header.
                'sectionheaderbackgroundcolour' => array(
                    'label' => new lang_string('sectionheaderbackgroundcolour', 'format_vsf'),
                    'help' => 'sectionheaderbackgroundcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultsectionheaderbackgroundcolour'])
                    )
                ),
                'sectionheaderbackgroundhvrcolour' => array(
                    'label' => new lang_string('sectionheaderbackgroundhvrcolour', 'format_vsf'),
                    'help' => 'sectionheaderbackgroundhvrcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultsectionheaderbackgroundhvrcolour'])
                    )
                ),
                'sectionheaderforegroundcolour' => array(
                    'label' => new lang_string('sectionheaderforegroundcolour', 'format_vsf'),
                    'help' => 'sectionheaderforegroundcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultsectionheaderforegroundcolour'])
                    )
                ),
                'sectionheaderforegroundhvrcolour' => array(
                    'label' => new lang_string('sectionheaderforegroundhvrcolour', 'format_vsf'),
                    'help' => 'sectionheaderforegroundhvrcolour',
                    'help_component' => 'format_vsf',
                    'element_type' => 'vsfcolourpopup',
                    'element_attributes' => array(
                        array('value' => $defaults['defaultsectionheaderforegroundhvrcolour'])
                    )
                ),
                'layoutcolumns' => array(
                    'label' => new lang_string('setlayoutcolumns', 'format_vsf'),
                    'help' => 'setlayoutcolumns',
                    'help_component' => 'format_vsf',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => new lang_string('one', 'format_vsf'),   // Default.
                              2 => new lang_string('two', 'format_vsf'))   // Two.
                    )
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    protected function get_course_format_colour_defaults() {
        $defaults = array();
        // Continue button.
        $defaults['defaultcontinuebackgroundcolour'] = get_config('format_vsf', 'defaultcontinuebackgroundcolour');
        if ($defaults['defaultcontinuebackgroundcolour'][0] == '#') {
            $defaults['defaultcontinuebackgroundcolour'] = substr($defaults['defaultcontinuebackgroundcolour'], 1);
        }
        $defaults['defaultcontinuetextcolour'] = get_config('format_vsf', 'defaultcontinuetextcolour');
        if ($defaults['defaultcontinuetextcolour'][0] == '#') {
            $defaults['defaultcontinuetextcolour'] = substr($defaults['defaultcontinuetextcolour'], 1);
        }
        // Section header.
        $defaults['defaultsectionheaderbackgroundcolour'] = get_config('format_vsf', 'defaultsectionheaderbackgroundcolour');
        if ($defaults['defaultsectionheaderbackgroundcolour'][0] == '#') {
            $defaults['defaultsectionheaderbackgroundcolour'] = substr($defaults['defaultsectionheaderbackgroundcolour'], 1);
        }
        $defaults['defaultsectionheaderbackgroundhvrcolour'] = get_config('format_vsf', 'defaultsectionheaderbackgroundhvrcolour');
        if ($defaults['defaultsectionheaderbackgroundhvrcolour'][0] == '#') {
            $defaults['defaultsectionheaderbackgroundhvrcolour'] = substr($defaults['defaultsectionheaderbackgroundhvrcolour'], 1);
        }
        $defaults['defaultsectionheaderforegroundcolour'] = get_config('format_vsf', 'defaultsectionheaderforegroundcolour');
        if ($defaults['defaultsectionheaderforegroundcolour'][0] == '#') {
            $defaults['defaultsectionheaderforegroundcolour'] = substr($defaults['defaultsectionheaderforegroundcolour'], 1);
        }
        $defaults['defaultsectionheaderforegroundhvrcolour'] = get_config('format_vsf', 'defaultsectionheaderforegroundhvrcolour');
        if ($defaults['defaultsectionheaderforegroundhvrcolour'][0] == '#') {
            $defaults['defaultsectionheaderforegroundhvrcolour'] = substr($defaults['defaultsectionheaderforegroundhvrcolour'], 1);
        }
        return $defaults;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $CFG, $COURSE;
        MoodleQuickForm::registerElementType('vsfcolourpopup', "$CFG->dirroot/course/format/vsf/js/vsf_colourpopup.php",
                'MoodleQuickForm_vsfcolourpopup');
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;
        $data = (array)$data;

        // Don't allow values from other formats to override the fixed defaults here.
        if (array_key_exists('hiddensections', $data)) {
            unset($data['hiddensections']);
        }

        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    } else if ($key === 'numsections') {
                        /* If previous format does not have the field 'numsections'
                           and $data['numsections'] is not set,
                           we fill it with the maximum section number from the DB. */
                        $maxsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', array($this->courseid));
                        if ($maxsection) {
                            // If there are no sections, or just default 0-section, 'numsections' will be set to default.
                            $data['numsections'] = $maxsection;
                        }
                    }
                }
            }
        }
        $changed = $this->update_format_options($data);
        if ($changed && array_key_exists('numsections', $data)) {
            // If the numsections was decreased, try to completely delete the orphaned sections (unless they are not empty).
            $numsections = (int)$data['numsections'];
            $maxsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                        WHERE course = ?', array($this->courseid));
            for ($sectionnum = $maxsection; $sectionnum > $numsections; $sectionnum--) {
                if (!$this->delete_section($sectionnum, false)) {
                    break;
                }
            }
        }
        return $changed;
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Updates the number of columns when the renderer detects that they are wrong.
     * @param int $layoutcolumns The layout columns to use.
     */
    public function update_vsf_columns_setting($layoutcolumns) {
        // Create data array.
        $data = array('layoutcolumns' => $layoutcolumns);

        $this->update_course_format_options($data);
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_vsf');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_vsf', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }
    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * This format is compatible with the React updates.
     */
    public function supports_components() {
        return true;  // I.e. Allows section drag and drop to work!
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'vsf' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_vsf');

        if (!($section instanceof section_info)) {
            $modinfo = course_modinfo::instance($this->courseid);
            $section = $modinfo->get_section_info($section->section);
        }
        $elementclass = $this->get_output_classname('content\\section\\availability');
        $availability = new $elementclass($this, $section);

        $rv['section_availability'] = $renderer->render($availability);

        return $rv;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_vsf_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'vsf'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
