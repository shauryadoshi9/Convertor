<?php
/**
 * MediaConverter Pro - FFmpeg & FFprobe Media Engine
 *
 * Wraps FFmpeg and FFprobe CLI for stream inspection, H.264/AAC transcoding,
 * LAME MP3 extraction, aspect ratio preservation, and real-time progress parsing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaConverter_FFmpeg {

	/**
	 * Path to FFmpeg executable.
	 *
	 * @var string
	 */
	private $ffmpeg_path;

	/**
	 * Path to FFprobe executable.
	 *
	 * @var string
	 */
	private $ffprobe_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$options = get_option( 'media_converter_options', array() );
		$this->ffmpeg_path  = ! empty( $options['ffmpeg_path'] ) ? $options['ffmpeg_path'] : MediaConverter_Installer::auto_detect_binary( 'ffmpeg' );
		$this->ffprobe_path = ! empty( $options['ffprobe_path'] ) ? $options['ffprobe_path'] : MediaConverter_Installer::auto_detect_binary( 'ffprobe' );
	}

	/**
	 * Get configured FFmpeg binary path.
	 *
	 * @return string
	 */
	public function get_ffmpeg_path() {
		return $this->ffmpeg_path;
	}

	/**
	 * Get configured FFprobe binary path.
	 *
	 * @return string
	 */
	public function get_ffprobe_path() {
		return $this->ffprobe_path;
	}

	/**
	 * Verify if FFmpeg is installed and callable.
	 *
	 * @return array ['available' => bool, 'version' => string, 'error' => string]
	 */
	public function check_ffmpeg() {
		$cmd = escapeshellarg( $this->ffmpeg_path ) . ' -version 2>&1';
		$output = @shell_exec( $cmd );

		if ( empty( $output ) || ! str_contains( strtolower( $output ), 'ffmpeg' ) ) {
			return array(
				'available' => false,
				'version'   => '',
				'error'     => 'FFmpeg executable not found or not executable at ' . esc_html( $this->ffmpeg_path ),
			);
		}

		$lines   = explode( "\n", trim( $output ) );
		$version = trim( $lines[0] ?? 'FFmpeg' );

		return array(
			'available' => true,
			'version'   => $version,
			'error'     => '',
		);
	}

	/**
	 * Verify if FFprobe is installed and callable.
	 *
	 * @return array ['available' => bool, 'version' => string, 'error' => string]
	 */
	public function check_ffprobe() {
		$cmd = escapeshellarg( $this->ffprobe_path ) . ' -version 2>&1';
		$output = @shell_exec( $cmd );

		if ( empty( $output ) || ! str_contains( strtolower( $output ), 'ffprobe' ) ) {
			return array(
				'available' => false,
				'version'   => '',
				'error'     => 'FFprobe executable not found or not executable at ' . esc_html( $this->ffprobe_path ),
			);
		}

		$lines   = explode( "\n", trim( $output ) );
		$version = trim( $lines[0] ?? 'FFprobe' );

		return array(
			'available' => true,
			'version'   => $version,
			'error'     => '',
		);
	}

	/**
	 * Probe media file to extract technical stream metadata and compute available qualities.
	 *
	 * @param string $filepath
	 * @return array
	 */
	public function probe_media( $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			return array(
				'success' => false,
				'error'   => 'Media file does not exist on disk.',
			);
		}

		$cmd = sprintf(
			'%s -v quiet -print_format json -show_format -show_streams %s',
			escapeshellarg( $this->ffprobe_path ),
			escapeshellarg( $filepath )
		);

		$output = @shell_exec( $cmd );
		if ( empty( $output ) ) {
			return array(
				'success' => false,
				'error'   => 'FFprobe failed to analyze media streams.',
			);
		}

		$data = json_decode( $output, true );
		if ( ! is_array( $data ) || empty( $data['streams'] ) ) {
			return array(
				'success' => false,
				'error'   => 'No valid audio or video streams detected in source file.',
			);
		}

		$video_stream = null;
		$audio_stream = null;

		foreach ( $data['streams'] as $stream ) {
			if ( 'video' === ( $stream['codec_type'] ?? '' ) && null === $video_stream ) {
				$video_stream = $stream;
			} elseif ( 'audio' === ( $stream['codec_type'] ?? '' ) && null === $audio_stream ) {
				$audio_stream = $stream;
			}
		}

		$format_info = $data['format'] ?? array();
		$duration    = floatval( $format_info['duration'] ?? ( $video_stream['duration'] ?? ( $audio_stream['duration'] ?? 0 ) ) );
		$bitrate     = intval( $format_info['bit_rate'] ?? 0 );
		$tags        = $format_info['tags'] ?? array();

		// Calculate available video qualities (strictly never upscaled).
		$available_video_qualities = array( 'original' );
		$width  = 0;
		$height = 0;
		$fps    = 30;

		if ( $video_stream ) {
			$width  = intval( $video_stream['width'] ?? 0 );
			$height = intval( $video_stream['height'] ?? 0 );

			if ( ! empty( $video_stream['r_frame_rate'] ) && str_contains( $video_stream['r_frame_rate'], '/' ) ) {
				$parts = explode( '/', $video_stream['r_frame_rate'] );
				$fps   = ( intval( $parts[1] ) > 0 ) ? round( intval( $parts[0] ) / intval( $parts[1] ), 2 ) : 30;
			}

			// Add supported tiers without upscaling.
			if ( $height >= 1080 ) {
				$available_video_qualities[] = '1080p';
			}
			if ( $height >= 720 ) {
				$available_video_qualities[] = '720p';
			}
			if ( $height >= 480 ) {
				$available_video_qualities[] = '480p';
			}
			if ( $height >= 360 ) {
				$available_video_qualities[] = '360p';
			}
		}

		// Available audio qualities.
		$available_audio_qualities = array( 'original', '320k', '256k', '192k', '128k' );
		$audio_bitrate_kbps = 0;
		if ( $audio_stream ) {
			$raw_audio_br = intval( $audio_stream['bit_rate'] ?? 0 );
			$audio_bitrate_kbps = $raw_audio_br > 0 ? round( $raw_audio_br / 1000 ) : 192;
		}

		return array(
			'success'                   => true,
			'has_video'                 => ( null !== $video_stream ),
			'has_audio'                 => ( null !== $audio_stream ),
			'duration'                  => $duration,
			'duration_formatted'        => gmdate( $duration >= 3600 ? 'H:i:s' : 'i:s', (int) $duration ),
			'bitrate'                   => $bitrate,
			'width'                     => $width,
			'height'                    => $height,
			'resolution'                => $width > 0 ? "{$width}x{$height}" : 'N/A',
			'fps'                       => $fps,
			'video_codec'               => $video_stream['codec_name'] ?? 'none',
			'audio_codec'               => $audio_stream['codec_name'] ?? 'none',
			'audio_sample_rate'         => $audio_stream['sample_rate'] ?? '44100',
			'audio_channels'            => $audio_stream['channels'] ?? 2,
			'audio_bitrate_kbps'        => $audio_bitrate_kbps,
			'available_video_qualities' => array_values( array_unique( $available_video_qualities ) ),
			'available_audio_qualities' => $available_audio_qualities,
			'tags'                      => array(
				'title'  => sanitize_text_field( $tags['title'] ?? $tags['TITLE'] ?? '' ),
				'artist' => sanitize_text_field( $tags['artist'] ?? $tags['ARTIST'] ?? '' ),
				'album'  => sanitize_text_field( $tags['album'] ?? $tags['ALBUM'] ?? '' ),
				'year'   => sanitize_text_field( $tags['date'] ?? $tags['DATE'] ?? '' ),
			),
		);
	}

	/**
	 * Execute FFmpeg transcoding job with real-time progress reporting.
	 *
	 * @param string $input_path
	 * @param string $output_path
	 * @param string $format 'mp4' or 'mp3'
	 * @param string $quality 'original', '1080p', '720p', '480p', '360p', '320k', '256k', '192k', '128k'
	 * @param callable $progress_callback function(float $percent, string $stage)
	 * @param float $total_duration Total media duration in seconds
	 * @param array $metadata Optional ID3 / container tags
	 * @param array $probe_data Optional pre-probed media metadata
	 * @param string|null $trim_start Optional start time (HH:MM:SS)
	 * @param string|null $trim_end Optional end time (HH:MM:SS)
	 * @return array ['success' => bool, 'error' => string, 'output_path' => string, 'file_size' => int]
	 */
	public function convert( $input_path, $output_path, $format, $quality, $progress_callback, $total_duration = 0, array $metadata = array(), array $probe_data = array(), $trim_start = null, $trim_end = null ) {
		if ( ! file_exists( $input_path ) ) {
			return array( 'success' => false, 'error' => 'Input media file does not exist.' );
		}

		// Progress file for real-time progress parsing.
		$progress_file = MEDIA_CONVERTER_TEMP_DIR . 'prog_' . wp_generate_password( 16, false ) . '.txt';

		$options = get_option( 'media_converter_options', array() );
		$timeout = isset( $options['max_conversion_duration_sec'] ) ? intval( $options['max_conversion_duration_sec'] ) : 3600;

		// If probe data wasn't provided, probe the media now
		if ( empty( $probe_data ) || ! isset( $probe_data['video_codec'] ) ) {
			$probe_data = $this->probe_media( $input_path );
		}

		// Build arguments based on format.
		$cmd_args = array(
			escapeshellarg( $this->ffmpeg_path ),
			'-y', // Overwrite output
		);

		// Fast seeking / clipping for time sections
		if ( ! empty( $trim_start ) ) {
			$cmd_args[] = '-ss';
			$cmd_args[] = escapeshellarg( $trim_start );
		}
		if ( ! empty( $trim_end ) ) {
			$cmd_args[] = '-to';
			$cmd_args[] = escapeshellarg( $trim_end );
		}

		$cmd_args[] = '-i';
		$cmd_args[] = escapeshellarg( $input_path );

		$is_stream_copy = false;

		if ( 'mp3' === strtolower( $format ) ) {
			// MP3 Audio Conversion.
			$bitrate = $this->resolve_audio_bitrate( $quality );

			$cmd_args[] = '-vn'; // Disable video stream
			$cmd_args[] = '-c:a libmp3lame';
			$cmd_args[] = '-b:a ' . escapeshellarg( $bitrate );
			$cmd_args[] = '-threads 0';
			$cmd_args[] = '-ar 44100';
			$cmd_args[] = '-id3v2_version 3';

			// Inject metadata if available.
			if ( ! empty( $metadata['title'] ) ) {
				$cmd_args[] = '-metadata title=' . escapeshellarg( $metadata['title'] );
			}
			if ( ! empty( $metadata['artist'] ) ) {
				$cmd_args[] = '-metadata artist=' . escapeshellarg( $metadata['artist'] );
			}
			if ( ! empty( $metadata['album'] ) ) {
				$cmd_args[] = '-metadata album=' . escapeshellarg( $metadata['album'] );
			}
		} else {
			// MP4 Video Conversion.
			$video_codec  = strtolower( $probe_data['video_codec'] ?? '' );
			$audio_codec  = strtolower( $probe_data['audio_codec'] ?? '' );
			$input_height = intval( $probe_data['height'] ?? 0 );

			// Codec compliance checks
			$is_h264_video = in_array( $video_codec, array( 'h264', 'avc1' ), true );
			$is_aac_audio  = in_array( $audio_codec, array( 'aac', 'mp4a' ), true );

			// Check if downscaling is strictly required
			$target_max_height = 0;
			switch ( $quality ) {
				case '1080p': $target_max_height = 1080; break;
				case '720p':  $target_max_height = 720; break;
				case '480p':  $target_max_height = 480; break;
				case '360p':  $target_max_height = 360; break;
				default:      $target_max_height = 0; break; // 'original'
			}

			$needs_downscale = ( $target_max_height > 0 && $input_height > $target_max_height );

			if ( $is_h264_video && $is_aac_audio && ! $needs_downscale ) {
				// Fast Stream Copy: Zero re-encoding overhead! Instant 5-10s remux for massive files.
				$is_stream_copy = true;
				$cmd_args[] = '-c copy';
				$cmd_args[] = '-movflags +faststart';
			} elseif ( $is_h264_video && ! $needs_downscale ) {
				// Video is already H.264 at requested resolution; copy video bit-for-bit, only transcode audio
				$cmd_args[] = '-c:v copy';
				$cmd_args[] = '-c:a aac';
				$cmd_args[] = '-b:a 192k';
				$cmd_args[] = '-movflags +faststart';
			} else {
				// Transcode video using high-performance multi-threading (-threads 0) and veryfast preset
				$scale_filter = $this->resolve_video_scale( $quality );

				$cmd_args[] = '-c:v libx264';
				$cmd_args[] = '-preset veryfast';
				$cmd_args[] = '-threads 0';
				$cmd_args[] = '-crf 22';
				$cmd_args[] = '-pix_fmt yuv420p'; // Universal browser compatibility

				if ( ! empty( $scale_filter ) ) {
					$cmd_args[] = '-vf ' . escapeshellarg( $scale_filter );
				}

				if ( $is_aac_audio ) {
					// Audio is already AAC, copy stream directly
					$cmd_args[] = '-c:a copy';
				} else {
					$cmd_args[] = '-c:a aac';
					$cmd_args[] = '-b:a 192k';
				}

				$cmd_args[] = '-movflags +faststart'; // Web-optimized streaming
			}
		}

		// Progress reporting flag (separate tokens).
		$cmd_args[] = '-progress';
		$cmd_args[] = escapeshellarg( $progress_file );
		$cmd_args[] = escapeshellarg( $output_path );

		$command_string = implode( ' ', $cmd_args );

		// Initial progress notification.
		if ( is_callable( $progress_callback ) ) {
			$initial_stage = $is_stream_copy ? 'Optimizing MP4 container streams' : 'Starting FFmpeg media pipeline';
			call_user_func( $progress_callback, 10.0, $initial_stage );
		}

		$err_log_file = MEDIA_CONVERTER_TEMP_DIR . 'ffmpeg_err_' . wp_generate_password( 8, false ) . '.log';
		$null_dev = ( stripos( PHP_OS, 'WIN' ) === 0 ) ? 'NUL' : '/dev/null';

		// Non-blocking file descriptors: Direct stdout to null and stderr to log file.
		// Eliminates Windows proc_open pipe buffer deadlocks.
		$descriptors = array(
			0 => array( 'pipe', 'r' ), // STDIN
			1 => array( 'file', $null_dev, 'w' ), // STDOUT (prevents pipe buffer block)
			2 => array( 'file', $err_log_file, 'a' ), // STDERR (captured cleanly)
		);

		$process = proc_open( $command_string, $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			@unlink( $progress_file );
			@unlink( $err_log_file );
			return array( 'success' => false, 'error' => 'Failed to initialize FFmpeg process.' );
		}

		if ( isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
			fclose( $pipes[0] ); // Close stdin
		}

		$start_time = time();
		$last_percent = 10.0;

		while ( true ) {
			$status = proc_get_status( $process );
			$running = $status['running'];

			// Parse progress file.
			if ( file_exists( $progress_file ) ) {
				$prog_content = @file_get_contents( $progress_file );
				if ( ! empty( $prog_content ) ) {
					$parsed = $this->parse_progress_content( $prog_content, $total_duration );
					if ( $parsed['percent'] > $last_percent ) {
						$last_percent = $parsed['percent'];
						if ( is_callable( $progress_callback ) ) {
							$stage_desc = ( 'mp3' === $format ) ? 'Encoding MP3 audio streams' : ( $is_stream_copy ? 'Remuxing & optimizing MP4 stream' : 'Transcoding H.264 video' );
							call_user_func( $progress_callback, $last_percent, $stage_desc );
						}
					}
				}
			}

			if ( ! $running ) {
				break;
			}

			// Check execution timeout.
			if ( ( time() - $start_time ) > $timeout ) {
				proc_terminate( $process, 9 );
				proc_close( $process );
				@unlink( $progress_file );
				@unlink( $err_log_file );
				@unlink( $output_path );
				return array( 'success' => false, 'error' => sprintf( 'Conversion timed out after %d seconds.', $timeout ) );
			}

			usleep( 250000 ); // 250ms sleep
		}

		$exit_code = proc_close( $process );
		@unlink( $progress_file );

		$stderr_log = file_exists( $err_log_file ) ? (string) file_get_contents( $err_log_file ) : '';
		@unlink( $err_log_file );

		if ( $exit_code !== 0 || ! file_exists( $output_path ) || filesize( $output_path ) === 0 ) {
			@unlink( $output_path );
			// Extract last few lines of stderr for concise error.
			$error_snippet = $this->extract_last_error_lines( $stderr_log );
			return array(
				'success' => false,
				'error'   => 'FFmpeg conversion error: ' . $error_snippet,
			);
		}

		// Finalize stage.
		if ( is_callable( $progress_callback ) ) {
			call_user_func( $progress_callback, 100.0, 'Finalizing output' );
		}

		return array(
			'success'     => true,
			'output_path' => $output_path,
			'file_size'   => filesize( $output_path ),
		);
	}

	/**
	 * Parse FFmpeg progress output to calculate exact percentage.
	 *
	 * @param string $content
	 * @param float $total_duration
	 * @return array
	 */
	private function parse_progress_content( $content, $total_duration ) {
		$lines = explode( "\n", $content );
		$out_time_us = 0;
		$is_end = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( str_starts_with( $line, 'out_time_us=' ) ) {
				$out_time_us = floatval( substr( $line, 12 ) );
			} elseif ( $line === 'progress=end' ) {
				$is_end = true;
			}
		}

		if ( $is_end ) {
			return array( 'percent' => 99.0 );
		}

		$out_seconds = $out_time_us / 1000000.0;
		if ( $total_duration > 0 ) {
			$percent = ( $out_seconds / $total_duration ) * 95.0; // Reserve 5% for finalizing
			return array( 'percent' => min( 98.0, max( 5.0, round( $percent, 1 ) ) ) );
		}

		return array( 'percent' => 50.0 );
	}

	/**
	 * Resolve audio bitrate string.
	 *
	 * @param string $quality
	 * @return string
	 */
	private function resolve_audio_bitrate( $quality ) {
		$map = array(
			'320k'     => '320k',
			'256k'     => '256k',
			'192k'     => '192k',
			'128k'     => '128k',
			'original' => '320k',
		);
		return $map[ $quality ] ?? '320k';
	}

	/**
	 * Resolve FFmpeg scale filter maintaining aspect ratio with even dimensions.
	 *
	 * @param string $quality
	 * @return string
	 */
	private function resolve_video_scale( $quality ) {
		switch ( $quality ) {
			case '1080p':
				return 'scale=-2:1080';
			case '720p':
				return 'scale=-2:720';
			case '480p':
				return 'scale=-2:480';
			case '360p':
				return 'scale=-2:360';
			case 'original':
			default:
				// Ensure width and height are divisible by 2 for H.264 yuv420p.
				return 'pad=ceil(iw/2)*2:ceil(ih/2)*2';
		}
	}

	/**
	 * Extract last meaningful error lines from FFmpeg stderr log.
	 *
	 * @param string $stderr
	 * @return string
	 */
	private function extract_last_error_lines( $stderr ) {
		$lines = explode( "\n", trim( $stderr ) );
		$last_lines = array_slice( $lines, -6 );
		$cleaned = array_filter( array_map( 'trim', $last_lines ) );
		return ! empty( $cleaned ) ? implode( ' | ', $cleaned ) : 'Unknown FFmpeg processing error.';
	}
}
