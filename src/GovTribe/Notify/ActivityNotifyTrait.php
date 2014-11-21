<?php namespace GovTribe\Notify;

use Project;

trait ActivityNotifyTrait {

	/**
	 * Get the activity message's notification based on a participant type's perspective.
	 *
	 * @param  object $perspective
	 * @return array
	 */
	public function getNotificationForPerspective($perspective)
	{
		$vars = $this->getNotificationVars($perspective);
		extract($vars);

		$payload = array('perspective' => $currentPerspective, 'message' => null, 'fire' => false);

		switch (true)
		{
			case $added && $personPerspective:
			case $updated && $projectPerspective:
			case $awarded && $vendorPerspective:
				$payload['fire'] = true;
				$payload['message'] = $this->assembleNotificationMessageForPerspective($perspective, $vars);
				break;

			default:
				break;
		}

		return $payload;
	}

	/**
	 * Assemble a notification message based on a perspective.
	 *
	 * @param  object $perspective
	 * @param  array  $vars
	 * @return string
	 */
	protected function assembleNotificationMessageForPerspective($perspective, array $vars)
	{
		extract($vars);

		// Assemble the message.
		$messageData = array(
			'preface' => null,
			'emoji' => null,
			'details' => array(),
			'currentPerspective' => $currentPerspective,
			'message' => null,
		);

		if (!$currentWorkflowStatus) return;

		switch ($currentWorkflowStatus)
		{
			case 'Presolicitation':

				if ($added || $workflowStatusChanged)
				{
					$messageData['preface'] = 'Presolicitation:';
					$messageData['emoji'] = 'bam';
				}
				else
				{
					$messageData['preface'] = "Update:";
					$messageData['emoji'] = 'star';
				}

				break;

			case 'Open':

				if ($added || $workflowStatusChanged)
				{
					$messageData['preface'] = 'Open for bid:';
					$messageData['emoji'] = 'bam';
				}
				else
				{
					$messageData['preface'] = "Update:";
					$messageData['emoji'] = 'star';
				}

				break;

			case 'Awarded':

				$messageData['preface'] = 'Awarded:';
				$messageData['emoji'] = 'moneybag';

				break;

			case 'Cancelled':

				$messageData['preface'] = 'Cancellation:';
				$messageData['emoji'] = 'surprise';

				break;

			default:

		}

		if ($messageData['preface'] === null) return null;

		//Add additional details to the message
		if ($addedADueDate) $messageData['details'][] = "Due: $dueDate";
		if ($changedTheDueDate) $messageData['details'][] = "Due date changed: $dueDate";
		if ($addedASetAsideType) $messageData['details'][] = "Set aside: $setAsideType";
		if ($changedTheSetAsideType) $messageData['details'][] = "Set aside changed: $setAsideType";

		if ($vendorName && $awardValue)
		{
			 $messageData['details'][] = "To: $vendorName ($awardValue)";
		}
		elseif ($vendorName) $messageData['details'][] = "To: $vendorName";

		if ($countOfFilesAdded && $packages)
		{
			 $messageData['details'][] = "$countOfFilesAdded file(s) added: $packages";
		}
		elseif ($countOfFilesAdded) $messageData['details'][] = "$countOfFilesAdded file(s) added";

		//Assemble the final message.
		switch ($currentPerspective)
		{
			case 'vendor':

				//$messageData['message'] .= $this->getEmojiForNotification($messageData['emoji']);
				//$messageData['message'] .= ' ';
				$messageData['message'] .= $perspective->name;
				$messageData['message'] .= ' ';
				$messageData['message'] .= 'was';
				$messageData['message'] .= ' ';
				$messageData['message'] .= strtolower($messageData['preface']);
				$messageData['message'] .= ' ';
				$messageData['message'] .= $projectName;

				if ($awardValue)
				{
					$messageData['message'] .= ' ';
					$messageData['message'] .= "($awardValue)";
				}

				break;

			case 'project':

				//$messageData['message'] .= $this->getEmojiForNotification($messageData['emoji']);
				//$messageData['message'] .= ' ';
				$messageData['message'] .= $messageData['preface'];
				$messageData['message'] .= ' ';
				$messageData['message'] .= $projectName;
				$messageData['message'] .= ', ';
				$messageData['message'] .= implode(', ', $messageData['details']);
				$messageData['message'] = rtrim($messageData['message'], ', ');

				break;

			case 'person':

				$messageData['preface'] = str_replace('Open for bid', 'opened for bid', $messageData['preface']);
				$messageData['preface'] = str_replace('Update', 'updated', $messageData['preface']);
				$messageData['preface'] = str_replace('Cancellation', 'canceled', $messageData['preface']);
				$messageData['preface'] = str_replace('Awarded', 'awarded', $messageData['preface']);
				$messageData['preface'] = str_replace('Presolicitation', 'released', $messageData['preface']);

				//$messageData['message'] .= $this->getEmojiForNotification($messageData['emoji']);
				//$messageData['message'] .= ' ';
				$messageData['message'] .= $perspective->name;
				$messageData['message'] .= ' ';
				$messageData['message'] .= $messageData['preface'];
				$messageData['message'] .= ' ';
				$messageData['message'] .= $projectName;
				$messageData['message'] .= ', ';
				$messageData['message'] .= implode(', ', $messageData['details']);
				$messageData['message'] = rtrim($messageData['message'], ', ');

				break;

			default:

				$messageData['message'] = null;

				break;
		}

		return $messageData['message'];
	}

	/**
	 * Get notification vars.
	 *
	 * @param  object $perspective
	 * @return array
	 */
	public function getNotificationVars($perspective)
	{
		$actions = $this->attributes['actions'];
		$project = $this->getLoadedProject();

		$fmt = \NumberFormatter::create ('en_US', \NumberFormatter::ORDINAL);

		$vars = array(
			'project' => $project,
			'projectName' => '"' . str_limit($project->name, 90) . '"',
			'projectVersionOrdinal' => $fmt->format($project->version),
			'dueDate' => isset($project->dueDate) ? $project->dueDate->format('n/j/y') : null,
			'setAsideType' => $project->setAsideType,
			'currentWorkflowStatus' => $project->workflowStatus,
			'updated' => isset($actions['updated']) ? true : false,
			'added' => isset($actions['added']) ? true : false,
			'currentPerspective' => null,
			'personPerspective' => class_basename($perspective) === 'Person' ? true : false,
			'projectPerspective' => class_basename($perspective) === 'Project' ? true : false,
			'vendorPerspective' => class_basename($perspective) === 'Vendor' ? true : false,
			'workflowStatusChanged' => isset($actions['changedTheStatusTo']) ? true : false,
			'addedASetAsideType' => isset($actions['addedASetAsideType']) ? true : false,
			'changedTheSetAsideType' => isset($actions['changedTheSetAsideType']) ? true : false,
			'addedADueDate' => isset($actions['addedADueDate']) ? true : false,
			'changedTheDueDate' => isset($actions['changedTheDueDate']) ? true : false,
			'countOfFilesAdded' => 0,
			'packages' => null,
			'awardValue' => $project->awardValue ? str_limit($project->awardValue, 15) : null,
			'awarded' => isset($actions['awarded']) ? true : false,
			'vendorName' => null,
		);

		if ($vars['personPerspective']) $vars['currentPerspective'] = 'person';
		if ($vars['projectPerspective']) $vars['currentPerspective'] = 'project';
		if ($vars['vendorPerspective']) $vars['currentPerspective'] = 'vendor';

		if (isset($actions['addedFiles']))
		{
			$packageNames = [];
			foreach ($actions['addedFiles'] as $packageName => $package)
			{
				$vars['countOfFilesAdded'] = $vars['countOfFilesAdded'] + count($package);
				if ($packageName !== 'Attachment') $packageNames[] = $packageName;
			}

			$packageNames = implode(', ', $packageNames);
			$vars['packages'] = rtrim($packageNames, ', ');
		}

		if (isset($project->vendors[0]['name']))
		{
			$vars['vendorName'] = $project->vendors[0]['name'];
		}

		return $vars;
	}

	/**
	 * Get emoji characters for notifications.
	 *
	 * @param  $code  string
	 * @return string
	 */
	protected function getEmojiForNotification($type)
	{
		$code = null;

		switch ($type) {
			case 'moneybag':
				$code = '&#x1f4b0;';
				break;
			case 'surprise':
				$code = '&#x1f622;';
				break;
			case 'happy':
				$code = '&#x1f60a;';
				break;
			case 'bam':
				$code = '&#x1f4a5;';
				break;
			case 'star':
				$code = '&#x1f31f;';
				break;
			case 'thumbsDown':
				$code = '&#x1f44e;';
				break;
		}

		return html_entity_decode($code, ENT_NOQUOTES,'UTF-8');
	}

	/**
	 * Get the activity message's loaded project.
	 *
	 * @return object
	 */
	public function getLoadedProject()
	{
		if ($this->attributes['activityType'] !== 'project')
		{
			return null;
		}

		foreach ($this->attributes['targets'] as $target)
		{
			if ($target['type'] === 'project') return Project::find($target['_id']);
		}
	}
}