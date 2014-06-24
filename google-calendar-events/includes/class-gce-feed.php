<?php


class GCE_Feed {
	
	public $id,
		   $feed_url,
		   $start,
		   $end,
		   $max,
		   $date_format,
		   $time_format,
		   $timezone_offset,
		   $cache,
		   $multiple_day_events,
		   $display_url;
	
	public $events = array();
	
	/*public $start = 0;
	private $end = 2145916800;*/
	
	public function __construct( $id ) {
		// Set the ID
		$this->id = $id;
		
		// Set up all other data based on the ID
		$this->setup_attributes();
		
		// Now create the Feed
		$this->create_feed();
		
		
	}
	
	private function setup_attributes() {
		
		$date_format = get_post_meta( $this->id, 'gce_date_format', true );
		$time_format = get_post_meta( $this->id, 'gce_time_format', true );
		
		$this->feed_url            = get_post_meta( $this->id, 'gce_feed_url', true );
		$this->start               = $this->set_feed_length( get_post_meta( $this->id, 'gce_retrieve_from', true ), 'start' );
		$this->end                 = $this->set_feed_length( get_post_meta( $this->id, 'gce_retrieve_until', true ), 'end' );
		$this->max                 = get_post_meta( $this->id, 'gce_retrieve_max', true );
		$this->date_format         = ( ! empty( $date_format ) ? $date_format : get_option( 'date_format' ) );
		$this->time_format         = ( ! empty( $time_format ) ? $time_format : get_option( 'time_format' ) );
		$this->timezone_offset     = get_post_meta( $this->id, 'gce_timezone_offset', true );
		$this->cache               = get_post_meta( $this->id, 'gce_cache', true );
		$this->multiple_day_events = get_post_meta( $this->id, 'gce_multi_day_events', true );
		
	}
	
	private function create_feed() {
		
		//Break the feed URL up into its parts (scheme, host, path, query)
		//echo $this->feed_url;
		
		//echo $this->start;
		
		$url_parts = parse_url( $this->feed_url );

		$scheme_and_host = $url_parts['scheme'] . '://' . $url_parts['host'];

		//Remove the exisitng projection from the path, and replace it with '/full-noattendees'
		$path = substr( $url_parts['path'], 0, strrpos( $url_parts['path'], '/' ) ) . '/full-noattendees';

		//Add the default parameters to the querystring (retrieving JSON, not XML)
		$query = '?alt=json&singleevents=true&sortorder=ascending';

		$gmt_offset = $this->timezone_offset * 3600;

		//Append the feed specific parameters to the querystring
		$query .= '&start-min=' . date( 'Y-m-d\TH:i:s', $this->start - $gmt_offset );
		$query .= '&start-max=' . date( 'Y-m-d\TH:i:s', $this->end - $gmt_offset );
		$query .= '&max-results=' . $this->max;

		if ( ! empty( $this->timezone_offset ) && $this->timezone_offset != 'default' ) {
			$query .= '&ctz=' . $this->timezone_offset;
		}

		//Put the URL back together
		$this->display_url = $scheme_and_host . $path . $query;
		
		$this->get_feed_data( $this->display_url );
		
		
	}
	
	public function display() {
		// OLD calendar return
		
		$display = new GCE_Display( $this->id, $this );
		
		return '<div class="gce-page-grid" id="gce-page-grid">' . $display->get_ajax() . '</div>';
	}

	
	private function get_feed_data( $url ) {
		$raw_data = wp_remote_get( $url, array(
				'sslverify' => false, //sslverify is set to false to ensure https URLs work reliably. Data source is Google's servers, so is trustworthy
				'timeout'   => 10     //Increase timeout from the default 5 seconds to ensure even large feeds are retrieved successfully
			) );
		
		//$this->events[] = $raw_data;

			//If $raw_data is a WP_Error, something went wrong
			if ( ! is_wp_error( $raw_data ) ) {
				//If response code isn't 200, something went wrong
				if ( 200 == $raw_data['response']['code'] ) {
					//Attempt to convert the returned JSON into an array
					$raw_data = json_decode( $raw_data['body'], true );

					//If decoding was successful
					if ( ! empty( $raw_data ) ) {
						//If there are some entries (events) to process
						if ( isset( $raw_data['feed']['entry'] ) ) {
							//Loop through each event, extracting the relevant information
							foreach ( $raw_data['feed']['entry'] as $event ) {
								$id          = esc_html( substr( $event['gCal$uid']['value'], 0, strpos( $event['gCal$uid']['value'], '@' ) ) );
								$title       = esc_html( $event['title']['$t'] );
								$description = esc_html( $event['content']['$t'] );
								$link        = esc_url( $event['link'][0]['href'] );
								$location    = esc_html( $event['gd$where'][0]['valueString'] );
								$start_time  = $this->iso_to_ts( $event['gd$when'][0]['startTime'] );
								$end_time    = $this->iso_to_ts( $event['gd$when'][0]['endTime'] );

								//Create a GCE_Event using the above data. Add it to the array of events
								$this->events[] = new GCE_Event( $this, $id, $title, $description, $location, $start_time, $end_time, $link );
							}
						}
					} else {
						//json_decode failed
						$this->error = __( 'Some data was retrieved, but could not be parsed successfully. Please ensure your feed URL is correct.', 'gce' );
					}
				} else {
					//The response code wasn't 200, so generate a helpful(ish) error message depending on error code 
					switch ( $raw_data['response']['code'] ) {
						case 404:
							$this->error = __( 'The feed could not be found (404). Please ensure your feed URL is correct.', 'gce' );
							break;
						case 403:
							$this->error = __( 'Access to this feed was denied (403). Please ensure you have public sharing enabled for your calendar.', 'gce' );
							break;
						default:
							$this->error = sprintf( __( 'The feed data could not be retrieved. Error code: %s. Please ensure your feed URL is correct.', 'gce' ), $raw_data['response']['code'] );
					}
				}
			}else{
				//Generate an error message from the returned WP_Error
				$this->error = $raw_data->get_error_message() . ' Please ensure your feed URL is correct.';
			}
	}
	
	//Convert an ISO date/time to a UNIX timestamp
	private function iso_to_ts( $iso ) {
		sscanf( $iso, "%u-%u-%uT%u:%u:%uZ", $year, $month, $day, $hour, $minute, $second );
		return mktime( $hour, $minute, $second, $month, $day, $year );
	}
	
	// Return feed start
	private function set_feed_length( $value, $type ) {
		switch ( $value ) {
			//Don't just use time() for 'now', as this will effectively make cache duration 1 second. Instead set to previous minute. 
			//Events in Google Calendar cannot be set to precision of seconds anyway
			case 'now':
				$return = mktime( date( 'H' ), date( 'i' ), 0, date( 'm' ), date( 'j' ), date( 'Y' ) );
				break;
			case 'today':
				$return = mktime( 0, 0, 0, date( 'm' ), date( 'j' ), date( 'Y' ) );
				break;
			case 'start_week':
				$return = mktime( 0, 0, 0, date( 'm' ), ( date( 'j' ) - date( 'w' ) ), date( 'Y' ) );
				break;
			case 'start_month':
				$return = mktime( 0, 0, 0, date( 'm' ), 1, date( 'Y' ) );
				break;
			case 'end_month':
				$return = mktime( 0, 0, 0, date( 'm' ) + 1, 1, date( 'Y' ) );
				break;
			//case 'date':
			//	$feed->feed_start = ;
			//	break;
			default:
				if( $type == 'start' ) {
					$return = 0; //any - 1970-01-01 00:00
				} else {
					$return = 2145916800;
				}
		}
		
		return $return;
	}
	
	public function get_display_url() {
		return $this->display_url;
	}
}
