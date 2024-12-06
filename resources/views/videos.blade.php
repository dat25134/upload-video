<!DOCTYPE html>
<html>
<head>
    <title>Video Player</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
</head>
<body>
    <div class="container">
        @foreach ($videosFiltered as $video)
            <video-js id="video_{{ $loop->index }}" class="vjs-default-skin" controls preload="auto" width="640" height="360">
                <source src="{{ asset("storage/" . $video) }}" type="application/x-mpegURL">
            </video-js>
        @endforeach
    </div>

    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @foreach ($videosFiltered as $video)
                videojs('video_{{ $loop->index }}', {
                    fluid: true,
                    html5: {
                        hls: {
                            enableLowInitialPlaylist: true,
                            smoothQualityChange: true,
                            overrideNative: true
                        }
                    }
                });
            @endforeach
        });
    </script>

    <style>
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }
        .video-js {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
            margin-bottom: 20px;
        }
    </style>
</body>
</html>
