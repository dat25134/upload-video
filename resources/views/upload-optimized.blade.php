<!DOCTYPE html>
<html>
<head>
    <title>Optimized File Upload</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .progress-container {
            width: 100%;
            margin: 20px 0;
        }
        .progress-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            background-color: #4CAF50;
            width: 0%;
            transition: width 0.5s ease-in-out;
        }
        .upload-status {
            margin-top: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <input type="file" id="file-input" />
        <button onclick="startUpload()">Upload</button>

        <div class="progress-container">
            <div class="progress-bar">
                <div id="progress" class="progress"></div>
            </div>
            <div id="upload-status" class="upload-status"></div>
        </div>
    </div>

    <script>
        class ChunkedUploader {
            constructor(file, options = {}) {
                this.file = file;
                this.chunkSize = options.chunkSize || 20 * 1024 * 1024; // 20MB chunks
                this.maxParallelUploads = options.maxParallelUploads || 3;
                this.retryAttempts = options.retryAttempts || 3;
                this.onProgress = options.onProgress || (() => {});
                this.onComplete = options.onComplete || (() => {});
                this.onError = options.onError || (() => {});

                this.chunks = this.createChunks();
                this.uploadedChunks = new Set();
                this.isUploading = false;
                this.workers = [];
                this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            }

            createChunks() {
                const chunks = [];
                let start = 0;
                while (start < this.file.size) {
                    chunks.push({
                        start,
                        end: Math.min(start + this.chunkSize, this.file.size)
                    });
                    start += this.chunkSize;
                }
                return chunks;
            }

            async start() {
                if (this.isUploading) return;
                this.isUploading = true;
                try {
                    const response = await fetch('/upload/init', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            filename: this.file.name,
                            totalChunks: this.chunks.length,
                            fileSize: this.file.size,
                            mimeType: this.file.type
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Failed to initialize upload session');
                    }

                    const { sessionId } = await response.json();
                    await this.uploadChunks(sessionId);
                    await this.finalizeUpload(sessionId);
                    this.onComplete();
                } catch (error) {
                    console.error(error);
                    this.onError(error);
                } finally {
                    this.cleanup();
                    this.isUploading = false;
                }
            }

            async uploadChunks(sessionId) {
                const chunksToUpload = this.chunks.filter((_, index) =>
                    !this.uploadedChunks.has(index)
                );

                while (chunksToUpload.length > 0) {
                    const uploadPromises = [];
                    const currentChunks = chunksToUpload.splice(0, this.maxParallelUploads);

                    for (const chunk of currentChunks) {
                        uploadPromises.push(this.uploadChunk(chunk, sessionId));
                    }

                    await Promise.all(uploadPromises);

                    // Cập nhật progress
                    const progress = this.uploadedChunks.size / this.chunks.length;
                    this.onProgress(progress);
                }
            }

            async uploadChunk(chunk, sessionId) {
                return new Promise((resolve, reject) => {
                    const worker = new Worker('/js/upload-worker.js');
                    this.workers.push(worker);

                    let attempts = 0;
                    const maxAttempts = this.retryAttempts;

                    const attemptUpload = () => {
                        worker.postMessage({
                            chunk,
                            chunkIndex: this.chunks.indexOf(chunk),
                            sessionId,
                            file: this.file,
                            csrfToken: this.csrfToken
                        });
                    };

                    worker.onmessage = (e) => {
                        const { success, chunkIndex, error } = e.data;

                        if (success) {
                            this.uploadedChunks.add(chunkIndex);
                            worker.terminate();
                            resolve();
                        } else {
                            attempts++;
                            if (attempts >= maxAttempts) {
                                worker.terminate();
                                reject(new Error(error));
                            } else {
                                setTimeout(() => attemptUpload(), 1000 * attempts);
                            }
                        }
                    };

                    worker.onerror = (error) => {
                        worker.terminate();
                        reject(error);
                    };

                    attemptUpload();
                });
            }

            async finalizeUpload(sessionId) {
                const response = await fetch('/upload/finalize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ sessionId })
                });

                if (!response.ok) throw new Error('Failed to finalize upload');
            }

            // Thêm phương thức để dọn dẹp workers
            cleanup() {
                this.workers.forEach(worker => worker.terminate());
                this.workers = [];
            }
        }

        function startUpload() {
            const fileInput = document.getElementById('file-input');
            const file = fileInput.files[0];
            if (!file) return;

            const progressBar = document.getElementById('progress');
            const statusElement = document.getElementById('upload-status');

            const uploader = new ChunkedUploader(file, {
                onProgress: (progress) => {
                    progressBar.style.width = `${progress * 100}%`;
                    statusElement.textContent = `Uploading: ${Math.round(progress * 100)}%`;
                },
                onComplete: () => {
                    statusElement.textContent = 'Upload completed!';
                },
                onError: (error) => {
                    statusElement.textContent = `Error: ${error.message}`;
                }
            });

            uploader.start();
        }
    </script>
</body>
</html>