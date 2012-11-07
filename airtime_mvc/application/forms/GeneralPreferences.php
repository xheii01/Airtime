<?php

class Application_Form_GeneralPreferences extends Zend_Form_SubForm
{

    public function init()
    {
        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/preferences_general.phtml'))
        ));

        $defaultFade = Application_Model_Preference::GetDefaultFade();
        if ($defaultFade == "") {
            $defaultFade = '0.5';
        }

        //Station name
        $this->addElement('text', 'stationName', array(
            'class'      => 'input_text',
            'label'      => 'Station Name',
            'required'   => false,
            'filters'    => array('StringTrim'),
            'value' => Application_Model_Preference::GetStationName(),
            'decorators' => array(
                'ViewHelper'
            )
        ));

        //Default station fade
        $this->addElement('text', 'stationDefaultFade', array(
            'class'      => 'input_text',
            'label'      => 'Default Fade (s):',
            'required'   => false,
            'filters'    => array('StringTrim'),
            'validators' => array(array('regex', false,
                array('/^[0-9]{1,2}(\.\d{1})?$/',
                'messages' => 'enter a time in seconds 0{.0}'))),
            'value' => $defaultFade,
            'decorators' => array(
                'ViewHelper'
            )
        ));

        $third_party_api = new Zend_Form_Element_Radio('thirdPartyApi');
        $third_party_api->setLabel('Website Widgets:');
        $third_party_api->setMultiOptions(array("Disabled",
                                            "Enabled"));
        $third_party_api->setValue(Application_Model_Preference::GetAllow3rdPartyApi());
        $third_party_api->setDecorators(array('ViewHelper'));
        $this->addElement($third_party_api);
        //
         // Add the description element
        $this->addElement('textarea', 'widgetCode', array(
            'label'      => 'Javascript Code:',
            'required'   => false,
            'readonly'   => true,
            'style'      => 'font-family: Consolas, "Liberation Mono", Courier, 
                monospace;',
            'class'      => 'input_text_area',
            'value' => self::getWidgetCode(), //$_SERVER["SERVER_NAME"],
            'decorators' => array(
                'ViewHelper'
            )
        ));

        /* Form Element for setting the Timezone */
        $timezone = new Zend_Form_Element_Select("timezone");
        $timezone->setLabel("Timezone");
        $timezone->setMultiOptions($this->getTimezones());
        $timezone->setValue(Application_Model_Preference::GetTimezone());
        $timezone->setDecorators(array('ViewHelper'));
        $this->addElement($timezone);

        /* Form Element for setting which day is the start of the week */
        $week_start_day = new Zend_Form_Element_Select("weekStartDay");
        $week_start_day->setLabel("Week Starts On");
        $week_start_day->setMultiOptions($this->getWeekStartDays());
        $week_start_day->setValue(Application_Model_Preference::GetWeekStartDay());
        $week_start_day->setDecorators(array('ViewHelper'));
        $this->addElement($week_start_day);
    }

    private function getTimezones()
    {
        $regions = array(
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Arctic' => DateTimeZone::ARCTIC,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC
        );

        $tzlist = array();

        foreach ($regions as $name => $mask) {
            $ids = DateTimeZone::listIdentifiers($mask);
            foreach ($ids as $id) {
                $tzlist[$id] = str_replace("_", " ", $id);
            }
        }

        return $tzlist;
    }

    private static function getWidgetCode() {
        
        $host = $_SERVER['SERVER_NAME'];
        $code = <<<CODE
<script src="http://$host/widgets/js/jquery-1.6.1.min.js" type="text/javascript"></script>
<script src="http://$host/widgets/js/jquery-ui-1.8.10.custom.min.js" type="text/javascript"></script>
<script src="http://$host/widgets/js/jquery.showinfo.js" type="text/javascript"></script>

<div id="headerLiveHolder" style="border: 1px solid #999999; padding: 10px;"></div>
<div id="onAirToday"></div>
<div id="scheduleTabs"></div>

<script type="text/javascript">
$(document).ready(function() {
    $("#headerLiveHolder").airtimeLiveInfo({
        sourceDomain: "http://$host",
        updatePeriod: 20 //seconds
    });

    $("#onAirToday").airtimeShowSchedule({
        sourceDomain: "http://$host",
        updatePeriod: 5, //seconds
        showLimit: 10
    });

    $("#scheduleTabs").airtimeWeekSchedule({
        sourceDomain:"http://$host",
        updatePeriod: 600 //seconds
    });
}
</script>
CODE;

        return $code;
    }

    private function getWeekStartDays()
    {
        $days = array(
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        );

        return $days;
    }
}
