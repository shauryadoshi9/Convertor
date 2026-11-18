<?php
/**
 * MediaConverter Pro - Conversion Orchestrator
 *
 * Coordinates validation, remote streaming/fetching, transcoding execution,
 * multi-stage state transitions, and file finalization.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_Converter {

	/**
	 * FFmpeg instance.
	 *
	 * @var MediaConverter_FFmpeg
	 */
	private $ffmpeg;

	/**
	 * Database instance.
	 *
	 * @var MediaConverter_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param MediaConverter_FFmpeg   $ffmpeg
	 * @param MediaConverter_Database $database
	 */
	public function __construct( MediaConverter_FFmpeg $ffmpeg, MediaConverter_Database $database ) {
		$this->ffmpeg   = $ffmpeg;
		$this->database = $database;
	}

	/**
	 * Execute conversion for a given job ID.
	 *
	 * @param int $job_id
	 * @return array ['success' => bool, 'error' => string, 'job' => object]
	 */
	public function process_job( $job_id ) {
		$job = $this->database->get_job_by_id( $job_id );
		if ( ! $job ) {
			return array( 'success' => false, 'error' => 'Job not found.' );
		}

		if ( in_array( $job->status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return array( 'success' => ( 'completed' === $job->status ), 'job' => $job );
		}

		// Stage 1: Validation.
		$this->database->update_job(
			$job_id,
			array(
				'status'     => 'validating',
				'stage'      => 'Source validation: In progress',
				'progress'   => 5,
				'started_at' => current_time( 'mysql' ),
			)
		);

		$input_file   = $job->input_path;
		$is_finalized = false;

		// If source is a remote direct URL and file not yet downloaded, download it securely.
		if ( 'url' === $job->source_type && ( empty( $input_file ) || ! file_exists( $input_file ) ) ) {
			$this->database->update_job(
				$job_id,
				array(
					'status'   => 'downloading',
					'stage'    => 'Extracting media stream from source...',
					'progress' => 10,
				)
			);
			$download_result = $this->download_remote_source(
				$job->source_url,
				$job->job_uuid,
				$job->output_format,
				$job->output_quality,
				$job_id,
				$job->trim_start ?? null,
				$job->trim_end ?? null
			);
			if ( ! $download_result['success'] ) {
				$this->database->mark_failed( $job_id, $download_result['error'] );
				return array( 'success' => false, 'error' => $download_result['error'] );
			}
			$input_file   = $download_result['filepath'];
			$is_finalized = ! empty( $download_result['is_finalized'] );
			$this->database->update_job( $job_id, array( 'input_path' => $input_file, 'progress' => 88, 'stage' => 'Media stream extracted successfully' ) );
		}

		if ( empty( $input_file ) || ! file_exists( $input_file ) ) {
			$err = 'Source file could not be located on the server.';
			$this->database->mark_failed( $job_id, $err );
			return array( 'success' => false, 'error' => $err );
		}

		// Probe media streams.
		$probe = $this->ffmpeg->probe_media( $input_file );
		if ( ! $probe['success'] ) {
			$this->database->mark_failed( $job_id, $probe['error'] );
			return array( 'success' => false, 'error' => $probe['error'] );
		}

		$duration = $probe['duration'] ?? 0;
		$this->database->update_job(
			$job_id,
			array(
				'duration_seconds' => $duration,
				'stage'            => 'Finalizing media streams...',
				'progress'         => 92,
			)
		);

		// Prepare output destination.
		$raw_base_name = pathinfo( $job->original_filename, PATHINFO_FILENAME );
		$clean_name    = sanitize_file_name( $raw_base_name );
		$output_filename = sprintf( '%s_%s.%s', $clean_name, substr( $job->job_uuid, 0, 8 ), $job->output_format );
		$output_path     = MEDIA_CONVERTER_OUTPUT_DIR . $output_filename;

		// High-Speed Direct Path: If stream processor already produced the exact finalized format
		// (e.g. 1080p MP4 or 320k MP3), move to output destination directly without redundant 2-hour re-encoding!
		if ( ! empty( $is_finalized ) && file_exists( $input_file ) ) {
			if ( ! @rename( $input_file, $output_path ) ) {
				@copy( $input_file, $output_path );
				@unlink( $input_file );
			}
			$result = array(
				'success'     => true,
				'output_path' => $output_path,
				'file_size'   => file_exists( $output_path ) ? filesize( $output_path ) : 0,
			);
		} else {
			// Stage 2: Media Processing & Transcoding for local uploads or unfinalized sources.
			$stage_label = ( 'mp3' === $job->output_format ) ? 'Audio encoding: In progress' : 'Media processing: In progress';
			$this->database->update_job(
				$job_id,
				array(
					'status'   => 'processing',
					'stage'    => $stage_label,
					'progress' => 20,
				)
			);

			// Progress callback closure.
			$progress_cb = function( $percent, $stage_text ) use ( $job_id ) {
				$this->database->update_progress( $job_id, $percent, $stage_text );
			};

			// Run FFmpeg conversion.
			$result = $this->ffmpeg->convert(
				$input_file,
				$output_path,
				$job->output_format,
				$job->output_quality,
				$progress_cb,
				$duration,
				$probe['tags'] ?? array(),
				$probe,
				$job->trim_start ?? null,
				$job->trim_end ?? null
			);
		}

		if ( ! $result['success'] ) {
			$this->database->mark_failed( $job_id, $result['error'] );
			return array( 'success' => false, 'error' => $result['error'] );
		}

		// Stage 3: Finalizing.
		$security = new MediaConverter_Security();
		$token    = $security->generate_download_token( $job_id );

		$this->database->mark_completed(
			$job_id,
			array(
				'output_filename' => $output_filename,
				'output_path'     => $output_path,
				'file_size'       => $result['file_size'],
				'download_token'  => $token,
				'stage'           => 'Your file is ready!',
			)
		);

		// If input was a temporary downloaded file from URL, remove temp raw file to save disk space.
		if ( 'url' === $job->source_type && file_exists( $input_file ) ) {
			@unlink( $input_file );
		}

		$updated_job = $this->database->get_job_by_id( $job_id );
		return array( 'success' => true, 'job' => $updated_job );
	}

	/**
	 * Download a remote direct media URL into temp directory securely.
	 *
	 * @param string $url
	 * @param string $uuid
	 * @param string $format
	 * @param string $quality
	 * @param int    $job_id
	 * @param string|null $trim_start
	 * @param string|null $trim_end
	 * @return array
	 */
	private function download_remote_source( $url, $uuid, $format = 'mp4', $quality = 'original', $job_id = 0, $trim_start = null, $trim_end = null ) {
		$validator = new MediaConverter_Validator();
		$val_check = $validator->validate_remote_url( $url );
		if ( ! $val_check['success'] ) {
			return array( 'success' => false, 'error' => $val_check['error'] );
		}

		// Check if URL is from YouTube, Vimeo or streaming platforms.
		$is_youtube   = ( str_contains( $url, 'youtube.com' ) || str_contains( $url, 'youtu.be' ) );
		$is_streaming = $is_youtube || str_contains( $url, 'vimeo.com' );

		if ( $is_streaming ) {
			return $this->download_with_ytdlp( $url, $uuid, $format, $quality, $job_id, $trim_start, $trim_end );
		}

		$temp_file = MEDIA_CONVERTER_TEMP_DIR . 'download_' . sanitize_file_name( $uuid ) . '.tmp';
		$fp = @fopen( $temp_file, 'w+' );
		if ( ! $fp ) {
			return array( 'success' => false, 'error' => 'Could not create temporary write stream for remote download.' );
		}

		// Stream download using cURL - Pro tier: Unlimited file size
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 3600 ); // 1 hour max for massive 2hr+ files
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) MediaConverter Pro/2.0' );

		$exec_ok = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_err  = curl_error( $ch );
		curl_close( $ch );
		fclose( $fp );

		if ( ! $exec_ok || $http_code < 200 || $http_code >= 300 ) {
			@unlink( $temp_file );
			$msg = ! empty( $curl_err ) ? $curl_err : sprintf( 'Remote server returned HTTP code %d.', $http_code );
			return array( 'success' => false, 'error' => 'Failed to download remote media: ' . $msg );
		}

		if ( filesize( $temp_file ) === 0 ) {
			@unlink( $temp_file );
			return array( 'success' => false, 'error' => 'Downloaded remote file was empty.' );
		}

		return array(
			'success'      => true,
			'filepath'     => $temp_file,
			'is_finalized' => false,
		);
	}

	/**
	 * Download media streams using yt-dlp with parallel multi-threading and real-time progress reporting.
	 *
	 * @param string $url
	 * @param string $uuid
	 * @param string $format
	 * @param string $quality
	 * @param int    $job_id
	 * @param string|null $trim_start
	 * @param string|null $trim_end
	 * @return array
	 */
	private function download_with_ytdlp( $url, $uuid, $format = 'mp4', $quality = 'original', $job_id = 0, $trim_start = null, $trim_end = null ) {
		$options = get_option( 'media_converter_options', array() );
		$ytdlp_bin = ! empty( $options['ytdlp_path'] ) ? $options['ytdlp_path'] : ( MEDIA_CONVERTER_PATH . 'bin/yt-dlp.exe' );
		if ( ! file_exists( $ytdlp_bin ) ) {
			$ytdlp_bin = MediaConverter_Installer::auto_detect_binary( 'yt-dlp' );
		}

		$ffmpeg_bin = $this->ffmpeg->get_ffmpeg_path();
		$is_mp3     = ( 'mp3' === strtolower( $format ) );
		$target_ext = $is_mp3 ? 'mp3' : 'mp4';
		$temp_file  = MEDIA_CONVERTER_TEMP_DIR . 'yt_' . sanitize_file_name( $uuid ) . '.' . $target_ext;
		$log_file   = MEDIA_CONVERTER_TEMP_DIR . 'ytdlp_log_' . sanitize_file_name( $uuid ) . '.txt';

		if ( $job_id ) {
			$this->database->update_job(
				$job_id,
				array(
					'stage'    => 'Connecting to high-speed stream sources...',
					'progress' => 12,
				)
			);
		}

		// Base command arguments optimized for maximum reliability and throughput.
		// Uses 4 concurrent fragments to avoid YouTube CDN anti-abuse throttling on long 2hr+ streams.
		$cmd_args = array(
			escapeshellarg( $ytdlp_bin ),
			'--no-playlist',
			'--no-warnings',
			'--no-cache-dir',
			'--no-mtime',
			'--socket-timeout', '30',
			'--retries', '10',
			'--fragment-retries', '10',
			'--concurrent-fragments', '4',
			'--ffmpeg-location', escapeshellarg( $ffmpeg_bin ),
			'--newline',
			'--progress-template', escapeshellarg( 'mc_prog:%(progress._percent_str)s|%(progress._speed_str)s|%(progress._eta_str)s|%(progress._total_bytes_estimate_str)s' ),
		);

		if ( $is_mp3 ) {
			// MP3 Direct Extraction: only download the tiny audio stream (50-100MB for 2 hours)
			// and extract pristine 320k MP3 directly via FFmpeg integration in seconds.
			$quality_map = array(
				'320k'     => '320K',
				'256k'     => '256K',
				'192k'     => '192K',
				'128k'     => '128K',
				'original' => '0',
			);
			$audio_q = $quality_map[ $quality ] ?? '320K';

			$cmd_args[] = '-x';
			$cmd_args[] = '--audio-format';
			$cmd_args[] = 'mp3';
			$cmd_args[] = '--audio-quality';
			$cmd_args[] = escapeshellarg( $audio_q );
			$cmd_args[] = '--embed-metadata';
			$cmd_args[] = '-f';
			$cmd_args[] = escapeshellarg( 'ba[ext=m4a]/ba/b' );
		} else {
			// MP4 Video: prioritize native H.264 (avc1) video + AAC audio so stream-copy remuxing is instant.
			switch ( $quality ) {
				case '360p':
					$format_selector = '18/bv*[vcodec^=avc][height<=360]+ba[ext=m4a]/bv*[height<=360][ext=mp4]+ba[ext=m4a]/bv*[height<=360]+ba/b[height<=360]/best';
					break;
				case '480p':
					$format_selector = 'bv*[vcodec^=avc][height<=480]+ba[ext=m4a]/bv*[height<=480][ext=mp4]+ba[ext=m4a]/bv*[height<=480]+ba/b[height<=480]/best';
					break;
				case '720p':
					$format_selector = '22/bv*[vcodec^=avc][height<=720]+ba[ext=m4a]/bv*[height<=720][ext=mp4]+ba[ext=m4a]/bv*[height<=720]+ba/b[height<=720]/best';
					break;
				case '1080p':
				case 'original':
				default:
					$format_selector = 'bv*[vcodec^=avc][height<=1080]+ba[ext=m4a]/bv*[vcodec^=avc]+ba[ext=m4a]/bv*[height<=1080][ext=mp4]+ba[ext=m4a]/bv*[height<=1080]+ba/b[height<=1080]/best';
					break;
			}

			$cmd_args[] = '--merge-output-format';
			$cmd_args[] = 'mp4';
			$cmd_args[] = '--postprocessor-args';
			$cmd_args[] = escapeshellarg( 'ffmpeg:-c:v copy -c:a aac -b:a 192k' );
			$cmd_args[] = '-f';
			$cmd_args[] = escapeshellarg( $format_selector );
		}

		// If time-range trimming is specified, download only the requested section
		if ( ! empty( $trim_start ) || ! empty( $trim_end ) ) {
			$t_start = ! empty( $trim_start ) ? $trim_start : '00:00:00';
			$t_end   = ! empty( $trim_end ) ? $trim_end : 'inf';
			$cmd_args[] = '--download-sections';
			$cmd_args[] = escapeshellarg( "*{$t_start}-{$t_end}" );
		}

		$cmd_args[] = '-o';
		$cmd_args[] = escapeshellarg( $temp_file );
		$cmd_args[] = escapeshellarg( $url );

		$command = implode( ' ', $cmd_args ) . ' > ' . escapeshellarg( $log_file ) . ' 2>&1';

		// Run detached with live progress polling
		$descriptors = array( 0 => array( 'pipe', 'r' ) );
		$proc = proc_open( $command, $descriptors, $pipes );
		if ( ! is_resource( $proc ) ) {
			return array( 'success' => false, 'error' => 'Failed to spawn stream downloader process.' );
		}
		if ( isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
			fclose( $pipes[0] );
		}

		$start_time = time();
		$max_dl_time = 3600; // 1 hour max download for massive 2hr+ files
		$last_pct = 12.0;

		while ( true ) {
			$status = proc_get_status( $proc );
			if ( file_exists( $log_file ) ) {
				$log_content = @file_get_contents( $log_file );
				if ( ! empty( $log_content ) ) {
					$lines = explode( "\n", $log_content );
					for ( $i = count( $lines ) - 1; $i >= max( 0, count( $lines ) - 25 ); $i-- ) {
						$line = trim( $lines[ $i ] );
						$raw_pct = 0;
						$speed   = '';
						$eta     = '';
						$total   = '';

						if ( preg_match( '/mc_prog:([0-9\.]+)%?\|([^\|]+)\|([^\|]+)\|([^\r\n]+)/', $line, $pm ) ) {
							$raw_pct = floatval( $pm[1] );
							$speed   = trim( $pm[2] );
							$eta     = trim( $pm[3] );
							$total   = trim( $pm[4] );
						} elseif ( preg_match( '/\[download\]\s+([\d\.]+)%\s+of\s+(?:~?\s*)?([^\s]+)\s+at\s+([^\s]+)\s+ETA\s+([^\r\n\s]+)/', $line, $pm ) ) {
							$raw_pct = floatval( $pm[1] );
							$total   = trim( $pm[2] );
							$speed   = trim( $pm[3] );
							$eta     = trim( $pm[4] );
						} elseif ( preg_match( '/\[download\]\s+([\d\.]+)%/', $line, $pm ) ) {
							$raw_pct = floatval( $pm[1] );
						}

						if ( $raw_pct > 0 ) {
							$scaled_pct = round( 12.0 + ( $raw_pct * 0.76 ), 1 );
							if ( $scaled_pct > $last_pct ) {
								$last_pct = $scaled_pct;
								$stage_desc = sprintf(
									'Downloading %s: %s%s%s%s',
									strtoupper( $format ),
									round( $raw_pct, 1 ) . '%',
									( ! empty( $total ) && 'N/A' !== $total ) ? " of {$total}" : '',
									( ! empty( $speed ) && 'N/A' !== $speed ) ? " at {$speed}" : '',
									( ! empty( $eta ) && 'N/A' !== $eta ) ? " (ETA: {$eta})" : ''
								);
								if ( $job_id ) {
									$this->database->update_job(
										$job_id,
										array(
											'progress' => $scaled_pct,
											'stage'    => $stage_desc,
										)
									);
								}
							}
							break;
						}
					}
				}
			}

			if ( ! $status['running'] ) {
				break;
			}

			if ( ( time() - $start_time ) > $max_dl_time ) {
				proc_terminate( $proc, 9 );
				proc_close( $proc );
				@unlink( $log_file );
				return array( 'success' => false, 'error' => 'Media download timed out.' );
			}

			usleep( 400000 ); // 400ms sleep
		}

		proc_close( $proc );

		$final_log = file_exists( $log_file ) ? (string) file_get_contents( $log_file ) : '';
		@unlink( $log_file );

		if ( ! file_exists( $temp_file ) || filesize( $temp_file ) === 0 ) {
			// Check if any matching file without .part exists
			$glob = glob( MEDIA_CONVERTER_TEMP_DIR . 'yt_' . sanitize_file_name( $uuid ) . '.*' );
			$matching = array_filter( $glob, function( $f ) { return ! str_ends_with( $f, '.part' ); } );
			if ( ! empty( $matching ) ) {
				$temp_file = reset( $matching );
			}
		}

		if ( ! file_exists( $temp_file ) || filesize( $temp_file ) === 0 ) {
			return array(
				'success' => false,
				'error'   => 'Could not extract media stream: ' . substr( trim( $final_log ), -300 ),
			);
		}

		return array(
			'success'      => true,
			'filepath'     => $temp_file,
			'is_finalized' => true,
		);
	}
}
