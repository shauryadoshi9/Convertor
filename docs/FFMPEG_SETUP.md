# MediaConverter Pro - FFmpeg & FFprobe Integration Manual

This technical specification details the FFmpeg process orchestration, command arguments, aspect ratio algorithms, stream probing, and progress calculation.

---

## 1. Technical Philosophy

MediaConverter Pro adheres strictly to the rule:
> **Never artificially upscale low-quality media or claim that conversion improves original quality.**

When an input media file is probed, its native stream parameters (width, height, frame rate, audio sample rate, codec) are extracted via `ffprobe`. Target resolution presets that exceed the original file dimensions are omitted from user options.

---

## 2. Stream Probing (`FFprobe`)

Before conversion begins, FFprobe inspects the input file:

```bash
ffprobe -v quiet -print_format json -show_format -show_streams "input_media.ext"
```

### Extracted Metadata Fields:
- **Video Stream:** `width`, `height`, `codec_name`, `r_frame_rate`, `bit_rate`, `pix_fmt`.
- **Audio Stream:** `codec_name`, `sample_rate`, `channels`, `bit_rate`.
- **Format Container:** `duration`, `bit_rate`, `tags` (title, artist, album, date).

### Quality Calculation Logic:
```php
$available_video_qualities = array('original');
if ($height >= 1080) $available_video_qualities[] = '1080p';
if ($height >= 720)  $available_video_qualities[] = '720p';
if ($height >= 480)  $available_video_qualities[] = '480p';
if ($height >= 360)  $available_video_qualities[] = '360p';
```

---

## 3. Video Transcoding Command Structure (MP4 / H.264 / AAC)

```bash
ffmpeg -y \
  -i "source_media.ext" \
  -c:v libx264 \
  -preset medium \
  -crf 22 \
  -pix_fmt yuv420p \
  -vf "scale=-2:1080" \
  -c:a aac \
  -b:a 192k \
  -movflags +faststart \
  -progress "progress.txt" \
  "output_file.mp4"
```

### Key Parameter Explanations:
- `-y`: Overwrite output file without prompting.
- `-c:v libx264`: High-efficiency H.264 video codec.
- `-preset medium`: Optimum balance between encoding speed and compression density.
- `-crf 22`: Constant Rate Factor 22 produces near-lossless visual quality for web and mobile delivery.
- `-pix_fmt yuv420p`: Standard 8-bit YUV 4:2:0 chroma subsampling required by iOS Safari, Android Chrome, and QuickTime.
- `-vf "scale=-2:1080"`: Scales height to 1080 pixels while calculating proportional width rounded to the nearest even number (divisible by 2).
- `-c:a aac -b:a 192k`: High-fidelity AAC stereo audio encoding.
- `-movflags +faststart`: Relocates metadata (`moov` atom) to the beginning of the MP4 file for zero-buffering browser playback.
- `-progress "progress.txt"`: Streams execution metrics for real-time progress parsing.

---

## 4. Audio Extraction Command Structure (MP3 / LAME)

```bash
ffmpeg -y \
  -i "source_media.ext" \
  -vn \
  -c:a libmp3lame \
  -b:a 320k \
  -ar 44100 \
  -id3v2_version 3 \
  -metadata title="Sample Song" \
  -metadata artist="Artist Name" \
  -progress "progress.txt" \
  "output_file.mp3"
```

### Key Parameter Explanations:
- `-vn`: Discards video streams completely to speed up audio demuxing.
- `-c:a libmp3lame`: Gold-standard LAME MP3 encoding engine.
- `-b:a 320k`: Maximum MP3 CBR bitrate (320 kbps) for crystal-clear acoustics.
- `-ar 44100`: Resamples audio to 44.1 kHz standard audio CD sample rate.
- `-id3v2_version 3`: Writes standard ID3v2.3 tags (Title, Artist, Album, Year) compatible with Apple Music, Spotify, Windows Media Player, and car infotainment systems.

---

## 5. Genuine Real-Time Progress Calculation

Unlike fake percentage countdowns, the plugin monitors actual FFmpeg progress:

1. FFmpeg writes to a unique progress file:
   ```ini
   frame=340
   fps=45.2
   out_time_us=4200000
   progress=continue
   ```
2. The plugin reads `out_time_us` (microseconds encoded so far):
   $$\text{out\_seconds} = \frac{\text{out\_time\_us}}{1,000,000}$$
3. The genuine progress percentage is calculated:
   $$\text{Progress \%} = \min\left(98.0, \frac{\text{out\_seconds}}{\text{total\_duration\_seconds}} \times 95.0\right)$$
4. The remaining 5% is reserved for container muxing and finalization (`progress=end`).
