<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

if (!isset($_GET['appointment'])) {
    header("location: appointments.php");
    exit();
}

$appointment_id = $_GET['appointment'];

// Verify and fetch appointment details
$sql = "SELECT a.*, p.pid, p.pname, p.pemail, p.pphoneno, d.docname 
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        WHERE a.appoid = ? AND a.docid = ?";
$stmt = $database->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $docid);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found or unauthorized access";
    header("location: appointments.php");
    exit();
}

// Update status to "In Session" if scheduled
if ($appointment['status'] == 'Scheduled') {
    $update_sql = "UPDATE appointment SET status = 'In Session' WHERE appoid = ?";
    $update_stmt = $database->prepare($update_sql);
    $update_stmt->bind_param("i", $appointment_id);
    $update_stmt->execute();
}

// Generate unique session ID
$session_id = "TELESESS-" . $appointment_id . "-" . bin2hex(random_bytes(4));

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemedicine Session - Dr. <?= $_SESSION['username'] ?></title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .telemedicine-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
            height: calc(100vh - 180px);
        }
        .video-container {
            background: #000;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        #localVideo {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 150px;
            border: 2px solid white;
            border-radius: 8px;
            z-index: 10;
        }
        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .video-controls {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            z-index: 10;
        }
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .control-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .control-btn.active {
            background: rgba(255,255,255,0.5);
        }
        .control-btn.end-call {
            background: #f44336;
        }
        .control-btn.end-call:hover {
            background: #d32f2f;
        }
        .sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }
        .session-info {
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        .session-id {
            font-family: monospace;
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .patient-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .patient-details h3 {
            margin: 0;
        }
        .patient-details p {
            margin: 5px 0 0;
            color: #6c757d;
        }
        .appointment-details {
            margin-bottom: 20px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            display: block;
            margin-bottom: 3px;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            border: 1px solid #eee;
            border-radius: 6px;
            overflow: hidden;
        }
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f9f9f9;
        }
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 18px 18px 0 18px;
        }
        .message.received {
            margin-right: auto;
            background: white;
            padding: 10px 15px;
            border-radius: 18px 18px 18px 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .chat-input {
            display: flex;
            border-top: 1px solid #eee;
            background: white;
        }
        .chat-input input {
            flex: 1;
            border: none;
            padding: 15px;
            outline: none;
        }
        .chat-input button {
            border: none;
            background: #4e73df;
            color: white;
            padding: 0 20px;
            cursor: pointer;
        }
        .session-recording {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        @media (max-width: 992px) {
            .telemedicine-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .sidebar {
                order: -1;
            }
            #localVideo {
                width: 120px;
                height: 90px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-video"></i> Telemedicine Session</h1>
            <div>
                <a href="patient_appointments.php?id=<?= $appointment['pid'] ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>
        </div>

        <div class="telemedicine-container">
            <div class="video-container">
                <video id="remoteVideo" autoplay playsinline></video>
                <video id="localVideo" autoplay playsinline muted></video>
                <div class="video-controls">
                    <button class="control-btn" id="toggleMic" title="Mute">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <button class="control-btn" id="toggleCamera" title="Turn off camera">
                        <i class="fas fa-video"></i>
                    </button>
                    <button class="control-btn" id="screenShare" title="Screen share">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button class="control-btn" id="recordBtn" title="Record session">
                        <i class="fas fa-circle"></i>
                    </button>
                    <button class="control-btn end-call" id="endCall" title="End call">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                </div>
            </div>
            
            <div class="sidebar">
                <div class="session-info">
                    <h3>Session Information</h3>
                    <p><strong>Session ID:</strong> <span class="session-id"><?= $session_id ?></span></p>
                    <p><strong>Started:</strong> <?= date('F j, Y g:i A') ?></p>
                </div>
                
                <div class="patient-info">
                    <div class="patient-avatar">
                        <?= strtoupper(substr($appointment['pname'], 0, 1)) ?>
                    </div>
                    <div class="patient-details">
                        <h3><?= htmlspecialchars($appointment['pname']) ?></h3>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['pphoneno']) ?></p>
                    </div>
                </div>
                
                <div class="appointment-details">
                    <div class="detail-item">
                        <span class="detail-label">Appointment Date</span>
                        <?= date('F j, Y', strtotime($appointment['appodate'])) ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Time</span>
                        <?= date('g:i A', strtotime($appointment['appotime'])) ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reason</span>
                        <?= htmlspecialchars($appointment['reason'] ?? 'General Consultation') ?>
                    </div>
                </div>
                
                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages">
                        <div class="message received">
                            <p>Hello Doctor, I'm ready for our session.</p>
                            <small>Just now</small>
                        </div>
                    </div>
                    <div class="chat-input">
                        <input type="text" placeholder="Type your message..." id="chatInput">
                        <button id="sendMessageBtn"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
                
                <div class="session-recording">
                    <h4><i class="fas fa-record-vinyl"></i> Session Recording</h4>
                    <div id="recordingStatus">Not recording</div>
                    <div id="recordingTimer">00:00:00</div>
                </div>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <!-- WebRTC and Socket.io Libraries -->
    <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    
    <script>
        // Configuration
        const config = {
            sessionId: "<?= $session_id ?>",
            appointmentId: "<?= $appointment_id ?>",
            userId: "doctor_<?= $docid ?>",
            userName: "Dr. <?= $_SESSION['username'] ?>",
            patientId: "patient_<?= $appointment['pid'] ?>",
            patientName: "<?= htmlspecialchars($appointment['pname']) ?>"
        };

        // WebRTC variables
        let localStream;
        let remoteStream;
        let peerConnection;
        let socket;
        let mediaRecorder;
        let recordedChunks = [];
        let recordingStartTime;
        let recordingInterval;
        
        // DOM elements
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const toggleMic = document.getElementById('toggleMic');
        const toggleCamera = document.getElementById('toggleCamera');
        const screenShare = document.getElementById('screenShare');
        const recordBtn = document.getElementById('recordBtn');
        const endCall = document.getElementById('endCall');
        const recordingStatus = document.getElementById('recordingStatus');
        const recordingTimer = document.getElementById('recordingTimer');
        
        // Initialize WebRTC connection
        async function init() {
            try {
                // Connect to signaling server
                socket = io('https://yoursignaling.server.com', {
                    query: {
                        sessionId: config.sessionId,
                        userId: config.userId,
                        role: 'doctor'
                    }
                });
                
                // Setup socket event listeners
                setupSocketListeners();
                
                // Get local media stream
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });
                localVideo.srcObject = localStream;
                
                // Create peer connection
                createPeerConnection();
                
                // Add local stream to peer connection
                localStream.getTracks().forEach(track => {
                    peerConnection.addTrack(track, localStream);
                });
                
            } catch (error) {
                console.error('Error initializing session:', error);
                alert('Failed to initialize telemedicine session. Please try again.');
            }
        }
        
        // Create RTCPeerConnection
        function createPeerConnection() {
            const pcConfig = {
                iceServers: [
                    { urls: 'relay1.expressturn.com:3478' },
                    Username
                    efSINGFTAQSQAMLG1H
                    Password
                    lYk08FLJMKKF9JsU

                ]
            };
            
            peerConnection = new RTCPeerConnection(pcConfig);
            
            // Handle ICE candidates
            peerConnection.onicecandidate = event => {
                if (event.candidate) {
                    socket.emit('ice-candidate', {
                        candidate: event.candidate,
                        target: config.patientId
                    });
                }
            };
            
            // Handle remote stream
            peerConnection.ontrack = event => {
                remoteVideo.srcObject = event.streams[0];
            };
            
            // Handle connection state changes
            peerConnection.onconnectionstatechange = event => {
                switch(peerConnection.connectionState) {
                    case 'connected':
                        console.log('Peer connection established');
                        break;
                    case 'disconnected':
                    case 'failed':
                        endSession();
                        break;
                    case 'closed':
                        console.log('Peer connection closed');
                        break;
                }
            };
        }
        
        // Setup socket.io event listeners
        function setupSocketListeners() {
            // When patient joins
            socket.on('patient-connected', async (patient) => {
                console.log('Patient connected:', patient);
                
                try {
                    // Create offer
                    const offer = await peerConnection.createOffer();
                    await peerConnection.setLocalDescription(offer);
                    
                    // Send offer to patient
                    socket.emit('offer', {
                        offer: offer,
                        target: patient.userId
                    });
                } catch (error) {
                    console.error('Error creating offer:', error);
                }
            });
            
            // Handle answer from patient
            socket.on('answer', async (answer) => {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
            });
            
            // Handle ICE candidates
            socket.on('ice-candidate', async (candidate) => {
                try {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                } catch (error) {
                    console.error('Error adding ICE candidate:', error);
                }
            });
            
            // Handle chat messages
            socket.on('chat-message', (message) => {
                addMessage(message, 'received');
            });
            
            // Handle session end
            socket.on('session-ended', () => {
                endSession();
            });
            
            // Handle errors
            socket.on('error', (error) => {
                console.error('Socket error:', error);
                alert('Connection error: ' + error.message);
            });
        }
        
        // Send chat message
        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === '') return;
            
            const messageData = {
                sender: config.userId,
                senderName: config.userName,
                message: message,
                timestamp: new Date().toISOString()
            };
            
            socket.emit('chat-message', messageData);
            addMessage(messageData, 'sent');
            chatInput.value = '';
        }
        
        // Add message to chat UI
        function addMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            
            const time = new Date(message.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            messageDiv.innerHTML = `
                <p>${message.message}</p>
                <small>${time}</small>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Toggle microphone
        function toggleMicrophone() {
            const audioTracks = localStream.getAudioTracks();
            audioTracks.forEach(track => {
                track.enabled = !track.enabled;
            });
            
            toggleMic.classList.toggle('active');
            toggleMic.title = audioTracks[0].enabled ? 'Mute' : 'Unmute';
        }
        
        // Toggle camera
        function toggleCamera() {
            const videoTracks = localStream.getVideoTracks();
            videoTracks.forEach(track => {
                track.enabled = !track.enabled;
            });
            
            toggleCamera.classList.toggle('active');
            toggleCamera.title = videoTracks[0].enabled ? 'Turn off camera' : 'Turn on camera';
        }
        
        // Start/stop screen sharing
        async function toggleScreenShare() {
            try {
                if (!screenShare.classList.contains('active')) {
                    const screenStream = await navigator.mediaDevices.getDisplayMedia({
                        video: true,
                        audio: false
                    });
                    
                    // Replace video track
                    const videoTrack = screenStream.getVideoTracks()[0];
                    const sender = peerConnection.getSenders().find(s => s.track.kind === 'video');
                    await sender.replaceTrack(videoTrack);
                    
                    // Show screen share in local video
                    localVideo.srcObject = new MediaStream([videoTrack, localStream.getAudioTracks()[0]]);
                    
                    screenShare.classList.add('active');
                    screenShare.title = 'Stop sharing';
                    
                    // Handle when user stops screen sharing
                    videoTrack.onended = () => {
                        toggleScreenShare();
                    };
                } else {
                    // Restore camera stream
                    const videoTrack = localStream.getVideoTracks()[0];
                    const sender = peerConnection.getSenders().find(s => s.track.kind === 'video');
                    await sender.replaceTrack(videoTrack);
                    
                    localVideo.srcObject = localStream;
                    screenShare.classList.remove('active');
                    screenShare.title = 'Share screen';
                }
            } catch (error) {
                console.error('Screen sharing error:', error);
            }
        }
        
        // Start/stop recording
        function toggleRecording() {
            if (!recordBtn.classList.contains('active')) {
                startRecording();
            } else {
                stopRecording();
            }
        }
        
        // Start recording session
        function startRecording() {
            recordedChunks = [];
            const options = { mimeType: 'video/webm' };
            
            try {
                // Combine both video streams
                const combinedStream = new MediaStream([
                    ...remoteVideo.srcObject.getVideoTracks(),
                    ...localVideo.srcObject.getAudioTracks()
                ]);
                
                mediaRecorder = new MediaRecorder(combinedStream, options);
                
                mediaRecorder.ondataavailable = event => {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    saveRecording();
                };
                
                mediaRecorder.start(1000); // Collect data every second
                recordingStartTime = Date.now();
                updateRecordingTimer();
                recordingInterval = setInterval(updateRecordingTimer, 1000);
                
                recordBtn.classList.add('active');
                recordBtn.innerHTML = '<i class="fas fa-stop"></i>';
                recordingStatus.textContent = 'Recording...';
            } catch (error) {
                console.error('Recording error:', error);
                alert('Recording failed to start: ' + error.message);
            }
        }
        
        // Stop recording session
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                clearInterval(recordingInterval);
                
                recordBtn.classList.remove('active');
                recordBtn.innerHTML = '<i class="fas fa-circle"></i>';
                recordingStatus.textContent = 'Recording saved';
            }
        }
        
        // Update recording timer
        function updateRecordingTimer() {
            const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
            const hours = Math.floor(elapsed / 3600).toString().padStart(2, '0');
            const minutes = Math.floor((elapsed % 3600) / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            
            recordingTimer.textContent = `${hours}:${minutes}:${seconds}`;
        }
        
        // Save recording to server
        function saveRecording() {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            const formData = new FormData();
            
            formData.append('recording', blob, `${config.sessionId}.webm`);
            formData.append('appointmentId', config.appointmentId);
            formData.append('doctorId', "<?= $docid ?>");
            formData.append('patientId', "<?= $appointment['pid'] ?>");
            formData.append('duration', recordingTimer.textContent);
            
            fetch('save_recording.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Recording saved successfully');
                } else {
                    console.error('Failed to save recording:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving recording:', error);
            });
        }
        
        // End session
        async function endSession() {
            try {
                // Stop all media tracks
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                }
                
                // Close peer connection
                if (peerConnection) {
                    peerConnection.close();
                }
                
                // Stop recording if active
                if (recordBtn.classList.contains('active')) {
                    stopRecording();
                }
                
                // Notify server session ended
                socket.emit('end-session', {
                    sessionId: config.sessionId,
                    appointmentId: config.appointmentId
                });
                
                // Update appointment status
                await fetch('update_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=<?= $appointment_id ?>&status=Completed`
                });
                
                // Redirect back to appointments
                window.location.href = 'patient_appointments.php?id=<?= $appointment['pid'] ?>';
                
            } catch (error) {
                console.error('Error ending session:', error);
            }
        }
        
        // Event listeners
        sendMessageBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
        
        toggleMic.addEventListener('click', toggleMicrophone);
        toggleCamera.addEventListener('click', toggleCamera);
        screenShare.addEventListener('click', toggleScreenShare);
        recordBtn.addEventListener('click', toggleRecording);
        endCall.addEventListener('click', () => {
            if (confirm('Are you sure you want to end this session?')) {
                endSession();
            }
        });
        
        // Initialize session
        init();
    </script>
</body>
</html>