<?php

class Application_Service_CalendarService
{
    private $currentUser;
    private $ccShowInstance;
    private $ccShow;

    public function __construct($instanceId = null)
    {
        if (!is_null($instanceId)) {
            $this->ccShowInstance = CcShowInstancesQuery::create()->findPk($instanceId);
            if (is_null($this->ccShowInstance)) {
                throw new Exception("Instance does not exist");
            }
            $this->ccShow = $this->ccShowInstance->getCcShow();
        }

        $service_user = new Application_Service_UserService();
        $this->currentUser = $service_user->getCurrentUser();
    }

    /**
     *
     * Enter description here ...
     */
    public function makeContextMenu()
    {
        $menu = array();
        $now = time();
        $baseUrl = Application_Common_OsPath::getBaseDir();
        $isAdminOrPM = $this->currentUser->isAdminOrPM();
        $isHostOfShow = $this->currentUser->isHostOfShow($this->ccShow->getDbId());

        //DateTime objects in UTC
        $startDT = $this->ccShowInstance->getDbStarts(null);
        $endDT = $this->ccShowInstance->getDbEnds(null);

        //timestamps
        $start = $startDT->getTimestamp();
        $end = $endDT->getTimestamp();

        //show has ended
        if ($now > $end) {
            if ($this->ccShowInstance->isRecorded()) {

                $ccFile = $this->ccShowInstance->getCcFiles();
                if (!isset($ccFile)) {
                     $menu["error when recording"] = array (
                         "name" => _("Record file doesn't exist"),
                         "icon" => "error");
                }else {
                    $menu["view_recorded"] = array(
                        "name" => _("View Recorded File Metadata"),
                        "icon" => "overview",
                        "url" => $baseUrl."library/edit-file-md/id/".$ccFile->getDbId());
                }

                //recorded show can be uploaded to soundcloud
                if (Application_Model_Preference::GetUploadToSoundcloudOption()) {
                    $scid = $ccFile->getDbSoundcloudId();

                    if ($scid > 0) {
                        $menu["soundcloud_view"] = array(
                            "name" => _("View on Soundcloud"),
                            "icon" => "soundcloud",
                            "url" => $ccFile->getDbSoundcloudLinkToFile());
                    }

                    $text = is_null($scid) ? _('Upload to SoundCloud') : _('Re-upload to SoundCloud');
                    $menu["soundcloud_upload"] = array(
                        "name"=> $text,
                        "icon" => "soundcloud");
                }
            } else {
                $menu["content"] = array(
                    "name"=> _("Show Content"),
                    "icon" => "overview",
                    "url" => $baseUrl."schedule/show-content-dialog");
            }
        } else {
            // Show content can be modified from the calendar if:
            // the user is admin or hosting the show,
            // the show is not recorded
            
            if ($now < $end && ($isAdminOrPM || $isHostOfShow) &&
            		!$this->ccShowInstance->isRecorded() ) {
            
            	$menu["schedule"] = array(
            			"name"=> _("Add / Remove Content"),
            			"icon" => "add-remove-content",
            			"url" => $baseUrl."showbuilder/builder-dialog/");
            	
            }
            
            if ($now < $start && ($isAdminOrPM || $isHostOfShow) &&
            		!$this->ccShowInstance->isRecorded() ) {
            
            	$menu["clear"] = array(
            			"name"=> _("Remove All Content"),
            			"icon" => "remove-all-content",
            			"url" => $baseUrl."schedule/clear-show");
            }

            //"Show Content" should be a menu item at all times except when
            //the show is recorded
            if (!$this->ccShowInstance->isRecorded()) {

                $menu["content"] = array(
                    "name"=> _("Show Content"),
                    "icon" => "overview",
                    "url" => $baseUrl."schedule/show-content-dialog");
            }

            //show is currently playing and user is admin
            if ($start <= $now && $now < $end && $isAdminOrPM) {

                if ($this->ccShowInstance->isRecorded()) {
                    $menu["cancel_recorded"] = array(
                        "name"=> _("Cancel Current Show"),
                        "icon" => "delete");
                } else {
                    $menu["cancel"] = array(
                        "name"=> _("Cancel Current Show"),
                        "icon" => "delete");
                }
            }

            $isRepeating = $this->ccShow->getFirstCcShowDay()->isRepeating();
            if (!$this->ccShowInstance->isRebroadcast() && $isAdminOrPM) {
                if ($isRepeating) {
                    $menu["edit"] = array(
                        "name" => _("Edit"),
                        "icon" => "edit",
                        "items" => array());

                    $menu["edit"]["items"]["all"] = array(
                        "name" => _("Edit Show"),
                        "icon" => "edit",
                        "url" => $baseUrl."Schedule/populate-show-form");

                    $menu["edit"]["items"]["instance"] = array(
                        "name" => _("Edit This Instance"),
                        "icon" => "edit",
                        "url" => $baseUrl."Schedule/populate-repeating-show-instance-form");
                } else {
                    $menu["edit"] = array(
                        "name"=> _("Edit Show"),
                        "icon" => "edit",
                        "_type"=>"all",
                        "url" => $baseUrl."Schedule/populate-show-form");
                }
            }

            //show hasn't started yet and user is admin
            if ($now < $start && $isAdminOrPM) {
                //show is repeating so give user the option to delete all
                //repeating instances or just the one
                if ($isRepeating) {
                    $menu["del"] = array(
                        "name"=> _("Delete"),
                        "icon" => "delete",
                        "items" => array());

                    $menu["del"]["items"]["single"] = array(
                        "name"=> _("Delete This Instance"),
                        "icon" => "delete",
                        "url" => $baseUrl."schedule/delete-show-instance");

                    $menu["del"]["items"]["following"] = array(
                        "name"=> _("Delete This Instance and All Following"),
                        "icon" => "delete",
                        "url" => $baseUrl."schedule/delete-show");
                } else {
                    $menu["del"] = array(
                        "name"=> _("Delete"),
                        "icon" => "delete",
                        "url" => $baseUrl."schedule/delete-show");
                }
            }
        }
        return $menu;
    }

    /**
     * 
     * Enter description here ...
     * @param DateTime $dateTime object to add deltas to
     * @param int $deltaDay delta days show moved
     * @param int $deltaMin delta minutes show moved
     */
    public static function addDeltas($dateTime, $deltaDay, $deltaMin)
    {
        $newDateTime = clone $dateTime;

        $days = abs($deltaDay);
        $mins = abs($deltaMin);

        $dayInterval = new DateInterval("P{$days}D");
        $minInterval = new DateInterval("PT{$mins}M");

        if ($deltaDay > 0) {
            $newDateTime->add($dayInterval);
        } elseif ($deltaDay < 0) {
            $newDateTime->sub($dayInterval);
        }

        if ($deltaMin > 0) {
            $newDateTime->add($minInterval);
        } elseif ($deltaMin < 0) {
            $newDateTime->sub($minInterval);
        }

        return $newDateTime;
    }

    private function validateShowMove($deltaDay, $deltaMin)
    {
        if (!$this->currentUser->isAdminOrPM()) {
            throw new Exception(_("Permission denied"));
        }

        if ($this->ccShow->getFirstCcShowDay()->isRepeating()) {
            throw new Exception(_("Can't drag and drop repeating shows"));
        }

        $today_timestamp = time();

        $startsDateTime = new DateTime($this->ccShowInstance->getDbStarts(), new DateTimeZone("UTC"));
        $endsDateTime = new DateTime($this->ccShowInstance->getDbEnds(), new DateTimeZone("UTC"));

        if ($today_timestamp > $startsDateTime->getTimestamp()) {
            throw new Exception(_("Can't move a past show"));
        }

        //the user is moving the show on the calendar from the perspective of local time.
        //incase a show is moved across a time change border offsets should be added to the localtime
        //stamp and then converted back to UTC to avoid show time changes!
        $showTimezone = $this->ccShow->getFirstCcShowDay()->getDbTimezone();
        $startsDateTime->setTimezone(new DateTimeZone($showTimezone));
        $endsDateTime->setTimezone(new DateTimeZone($showTimezone));

        $newStartsDateTime = self::addDeltas($startsDateTime, $deltaDay, $deltaMin);
        $newEndsDateTime = self::addDeltas($endsDateTime, $deltaDay, $deltaMin);

        //convert our new starts/ends to UTC.
        $newStartsDateTime->setTimezone(new DateTimeZone("UTC"));
        $newEndsDateTime->setTimezone(new DateTimeZone("UTC"));

        if ($today_timestamp > $newStartsDateTime->getTimestamp()) {
            throw new Exception(_("Can't move show into past"));
        }

        //check if show is overlapping
        $overlapping = Application_Model_Schedule::checkOverlappingShows(
            $newStartsDateTime, $newEndsDateTime, true, $this->ccShowInstance->getDbId());
        if ($overlapping) {
            throw new Exception(_("Cannot schedule overlapping shows"));
        }

        if ($this->ccShow->isRecorded()) {
            //rebroadcasts should start at max 1 hour after a recorded show has ended.
            $minRebroadcastStart = self::addDeltas($newEndsDateTime, 0, 60);
            //check if we are moving a recorded show less than 1 hour before any of its own rebroadcasts.
            $rebroadcasts = CcShowInstancesQuery::create()
                ->filterByDbOriginalShow($this->ccShow->getDbId())
                ->filterByDbStarts($minRebroadcastStart->format('Y-m-d H:i:s'), Criteria::LESS_THAN)
                ->find();

            if (count($rebroadcasts) > 0) {
                throw new Exception(_("Can't move a recorded show less than 1 hour before its rebroadcasts."));
            }
        }

        if ($this->ccShow->isRebroadcast()) {
            $recordedShow = CcShowInstancesQuery::create()
                ->filterByCcShow($this->ccShowInstance->getDbOriginalShow())
                ->findOne();
            if (is_null($recordedShow)) {
                $this->ccShowInstance->delete();
                throw new Exception(_("Show was deleted because recorded show does not exist!"));
            }

            $recordEndDateTime = new DateTime($recordedShow->getDbEnds(), new DateTimeZone("UTC"));
            $newRecordEndDateTime = self::addDeltas($recordEndDateTime, 0, 60);

            if ($newStartsDateTime->getTimestamp() < $newRecordEndDateTime->getTimestamp()) {
                throw new Exception(_("Must wait 1 hour to rebroadcast."));
            }
        }
        return array($newStartsDateTime, $newEndsDateTime);
    }

    public function moveShow($deltaDay, $deltaMin)
    {
        try {
            $con = Propel::getConnection();
            $con->beginTransaction();

            list($newStartsDateTime, $newEndsDateTime) = $this->validateShowMove(
                $deltaDay, $deltaMin);

            $this->ccShowInstance
                ->setDbStarts($newStartsDateTime)
                ->setDbEnds($newEndsDateTime)
                ->save();

            if (!$this->ccShowInstance->getCcShow()->isRebroadcast()) {
                //we can get the first show day because we know the show is
                //not repeating, and therefore will only have one show day entry
                $ccShowDay = $this->ccShow->getFirstCcShowDay();
                $showTimezone = new DateTimeZone($ccShowDay->getDbTimezone());
                $ccShowDay
                    ->setDbFirstShow($newStartsDateTime->setTimezone($showTimezone))
                    ->setDbLastShow($newEndsDateTime->setTimezone($showTimezone))
                    ->save();
            }

            Application_Service_SchedulerService::updateScheduleStartTime(
                array($this->ccShowInstance->getDbId()), null, $newStartsDateTime);

            $con->commit();
            Application_Model_RabbitMq::PushSchedule();
        } catch (Exception $e) {
            $con->rollback();
            return $e->getMessage();
        }
    }

    public function resizeShow($deltaDay, $deltaMin)
    {
        try {
            $con = Propel::getConnection();
            $con->beginTransaction();

            $con->commit();
            Application_Model_RabbitMq::PushSchedule();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}
