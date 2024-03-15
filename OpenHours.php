<?php

class OpenHours {
	public string $output_format = 'g:ia';
	public array $full_day_array;
	public array $control_hours = [];
	public array $open_hours = [];
	public array $label_hours = [];
	protected array $base_range;
	protected bool $has_date = false;
	protected bool $round = false;
	protected int $rounding_type = 0;
	private array $interval_reference;
	private string $date_string;
	private int $interval = 30;
	private bool $calculated = false;

	/**
	 * Instantiate a new OpenHours object
	 *
	 * @param int|null $interval The interval of minutes per day that is tracked.
	 *  It is far less resource intensive to use a larger interval. Default is 30 minutes.
	 * @param bool $has_date Attempts to retain a date along with the time.
	 *  If no date can be determined from data passed in, the date will default to the current day.
	 */
	public function __construct(int|null $interval = null, bool $has_date = false)
	{
		if (isset($interval)) {
			$this->interval = $interval;
		}
		$this->setBaseRange();
	}
	public function __toString(): string
	{
		if ($this->calculated) {
			// Return string formatted range(s)
			return 'This is an OpenHours timerange string.';
		}
		return '';
	}
	public function reset(): void
	{ // Reset all but base_range, output_format, has_date, and interval
		$this->full_day_array = [];
		$this->control_hours = [];
		$this->open_hours = [];
		$this->label_hours = [];
		$this->round = false;
		$this->rounding_type = false;
		unset($this->date_string);
		$this->calculated = false;
	}
	public function setInterval(int $interval): void
	{
		// Divisible evenly into 60?
		$this->interval = $interval;
		$this->setBaseRange();
		$this->calculated = false;
	}
	public function getInterval(): int
	{
		return $this->interval;
	}
	public function addControlHours(int|string $open, int|string $close, string|null $description = null): void
	{
		$this->calculated = false;
		$range = [];

		// If no value, assume closed, clear internal store
		if ($open == '' || $close == '') {
			$this->open_hours = $this->base_range;
			if ($description) {
				$label_hours = array_fill_keys(array_keys($range), [$description]);
				$label_hours = $label_hours + $this->base_range;
				ksort($label_hours);
				$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
			}
			return;
		}

		// If timestamp, we don't use strtotime on the input
		if ($this->isValidTimestamp($open)) {
			list($ts, $oh, $om) = explode(' ', date('U G i', $open)); // open hour and minute
		} else {
			list($ts, $oh, $om) = explode(' ', date('U G i', strtotime($open))); // open hour and minute
		}
		if ($this->isValidTimestamp($close)) {
			list($ch, $cm)      = explode(' ', date('G i', $close));  // close hour and minute
		} else {
			list($ch, $cm)      = explode(' ', date('G i', strtotime($close)));  // close hour and minute
		}

		// Handle a "closing at midnight" scenario
		if ($ch + $cm == 0 && $oh.$om > 0) {
			$ch = 24;
		}

		if ($oh.$om > $ch.$cm) {
			// Invalid range; set as closed
			$this->control_hours = $this->base_range;
			if ($description) {
				$label_hours = array_fill_keys(array_keys($range), [$description]);
				$label_hours = $label_hours + $this->base_range;
				ksort($label_hours);
				$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
			}
			return;
		}

		// Determine if a date should be stored
		if (date('Ymd') != date('Ymd', $ts)) {
			$this->has_date = true;
			$this->date_string = date('Y-m-d', $ts);
		}

		$split = 60 / $this->interval;
		$om    = intdiv($om,$this->interval) * $this->interval; // sanitize the minute value (forces to a derivative of "interval")
		$cm    = intdiv($cm,$this->interval) * $this->interval; // sanitize the minute value (forces to a derivative of "interval")
		$range = range(($oh*$split)+($om/$this->interval), (($ch)*$split)+($cm/$this->interval)); // range of associated keys
		$range = array_fill_keys($range, true);                 // range of "interval" times of the day, set to 1, from open->close

		if ($description) {
			$label_hours = array_fill_keys(array_keys($range), [$description]);
			$label_hours = $label_hours + $this->base_range;
			ksort($label_hours);
			$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
		}

		// Merge... New comes first in the + usage order
		$this->control_hours = $range + $this->control_hours;
		ksort($this->control_hours);
	}
	public function addOpenHours(int|string $open, int|string $close, string|null $description = null): void
	{
		// If we calculated a value previously, calling this method will invalidate that calculated value.
		$this->calculated = false;
		$range = [];

		// If no value, assume closed, clear internal store
		if ($open == '' || $close == '') {
			$this->open_hours = $this->base_range;
			if ($description) {
				$label_hours = array_fill_keys(array_keys($range), [$description]);
				$label_hours = $label_hours + $this->base_range;
				ksort($label_hours);
				$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
			}
			return;
		}

		// Merge.
		// If timestamp, we don't use strtotime on the input
		if ($this->isValidTimestamp($open)) {
			list($ts, $oh, $om) = explode(' ', date('U G i', $open)); // open hour and minute
		} else {
			list($ts, $oh, $om) = explode(' ', date('U G i', strtotime($open))); // open hour and minute
		}
		if ($this->isValidTimestamp($close)) {
			list($ch, $cm)      = explode(' ', date('G i', $close));  // close hour and minute
		} else {
			list($ch, $cm)      = explode(' ', date('G i', strtotime($close)));  // close hour and minute
		}

		// Handle a "closing at midnight" scenario
		if ($ch + $cm == 0 && $oh.$om >= 0) {
			$ch = 24;
		}

		if ($oh.$om > $ch.$cm) {
			// Invalid range; set as closed
			$this->open_hours = $this->base_range;
			if ($description) {
				$label_hours = array_fill_keys(array_keys($range), [$description]);
				$label_hours = $label_hours + $this->base_range;
				ksort($label_hours);
				$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
			}
			return;
		}

		// Determine if a date should be stored
		if (date('Ymd') != date('Ymd', $ts)) {
			$this->has_date = true;
			$this->date_string = date('Y-m-d', $ts);
		} else {
			$this->has_date = true;
			$this->date_string = date('Y-m-d');
		}

		$split = 60 / $this->interval;
		$om    = intdiv($om,$this->interval) * $this->interval; // sanitize the minute value (forces to a derivative of "interval")
		$cm    = intdiv($cm,$this->interval) * $this->interval; // sanitize the minute value (forces to a derivative of "interval")
		$range = range(($oh*$split)+($om/$this->interval), (($ch)*$split)+($cm/$this->interval)); // range of associated keys
		$range = array_fill_keys($range, true);                 // range of "interval" times of the day, set to 1, from open->close

		if ($description) {
			$label_hours = array_fill_keys(array_keys($range), [$description]);
			$label_hours = $label_hours + $this->base_range;
			ksort($label_hours);
			$this->label_hours = array_map([$this, 'merge_first_level_array'], $label_hours, $this->label_hours);
		}

		// Merge... New comes first in the + usage order
		$this->open_hours = $range + $this->open_hours;
		ksort($this->open_hours);
	}
	/**
	 * @return array A multidimensional array containing open/close values.
	 *  The first index is the group (in the case of a span of time when not open).
	 *  The second indices are the [0] open, and [1] close times.
	 */
	public function getHours(): array
	{
		if (!$this->calculated) {
			$this->generateFullDayArray();
		}
		// return $this->full_day_array;
		// $check = $this->all_boolean_array($this->full_day_array);

		// if ($check === true) {
		// 	return [0 => ['Open All Day']];
		// }
		// if ($check === false) {
		// 	return [0 => ['Closed']];
		// }

		return $this->formattedHours();
	}
	public function setFormat(string $format): void
	{
		// Remove all but compatible date() format strings for this purpose
		$format = preg_replace('/[^,-:aABgGhHiseIOPTZ\s]/', '', $format);
		if ($format) {
			$this->output_format = $format;
		}
	}
	private function generateFullDayArray(): void
	{
		$this->full_day_array = $this->base_range;
		$this->full_day_array = $this->open_hours + $this->full_day_array;
		if (count($this->control_hours)) {
			$this->full_day_array = array_intersect_assoc($this->full_day_array, $this->control_hours);
		}

		// Because OpenHours must be a range, a single slot is an invalid value; remove it
		$count = 1;
		foreach ($this->full_day_array as $time_slot => $val) {
			if (array_key_exists($time_slot+1, $this->full_day_array)) {
				$count++;
			} else {
				if ($count == 1) {
					unset($this->full_day_array[$time_slot]);
					$count = 1;
				}
			}
		}
		$this->full_day_array = $this->full_day_array + $this->base_range;
		ksort($this->full_day_array);

		$this->calculated = true;
	}
	private function setBaseRange(): void
	{
		$this->interval_reference = range(0, 24*60, $this->interval);
		$this->base_range = array_fill_keys(array_keys($this->interval_reference), false);
	}
	// Merges an index-based array's values, assuming the values are arrays themselves
	private function merge_first_level_array($a1, $a2) {
		if ($a1 && $a2) return array_merge($a1,$a2);
		if (!$a1) return $a2;
		if (!$a2) return $a1;
		return null;
	}
	private function isValidTimestamp(int|string $timestamp): bool
	{ // https://stackoverflow.com/a/2524761/155421
		return ((int) (string) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= PHP_INT_MIN);
	}
	// https://stackoverflow.com/a/6850548/155421
	// Check and return ALL true values, all false, or neither (return null)
	private function all_boolean_array($arr) {
		if (in_array(false, $arr, true) === false) {
			return true;
		} else if (in_array(true, $arr, true) === false) {
			return false;
		} else {
			return null;
		}
	}
	private function formattedHours() {
		// find all open/close times
		// TODO: The label(s) might break apart various other timespans; find a way to fix multiple instances of labels
		$start = $this->full_day_array[0];
		$business_hours = null;
		$count = 0;
		if ($start === true) {
			$business_hours[$count][0] = $this->minutesToTime($this->interval_reference[0]);
		}
		for ($i = 0; $i < count($this->full_day_array); $i++) {
			if ($i+1 != count($this->full_day_array)) {
				if ($this->full_day_array[$i] !== $this->full_day_array[$i+1]) {
					if ($this->full_day_array[$i] === true) {
						$business_hours[$count][1] = $this->minutesToTime($this->interval_reference[$i]);
						if ($this->has_date) {
							$business_hours[$count]['open'] = strtotime("{$this->date_string} {$business_hours[$count][0]}");
							$business_hours[$count]['close'] = strtotime("{$this->date_string} {$business_hours[$count][1]}");
							if (!empty($this->label_hours) && $this->label_hours[$i]) {
								$business_hours[$count]['description'] = implode(', ', $this->label_hours[$i]);
							}
						}
						$count++;
					} else {
						$business_hours[$count][0] = $this->minutesToTime($this->interval_reference[$i+1]);
					}
				}
			} else {
				if (isset($business_hours[$count][0]) && !isset($business_hours[$count][1])) {
					$business_hours[$count][1] = $this->minutesToTime(60*24);
					if ($this->has_date) {
						$business_hours[$count]['open'] = strtotime("{$this->date_string} {$business_hours[$count][0]}");
						$business_hours[$count]['close'] = strtotime("{$this->date_string} {$business_hours[$count][1]}");
					}
				}
			}
		}
		if ($this->full_day_array[count($this->full_day_array)-1] === true) {
			$business_hours[count($business_hours)-1]['close'] = strtotime("{$this->date_string} midnight +1 day");
		}

		return $business_hours ? $business_hours : [];
	}
	private function minutesToTime($minutes) {
		return date($this->output_format, strtotime("00:00 + {$minutes} minutes"));
	}
}
