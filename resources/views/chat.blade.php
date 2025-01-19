<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="shortcut icon" href="{{ asset('assets/images/whatsapp.png') }}" type="image/jpg">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
    <meta name="csrf-token" id="csrf-token" content="{{ csrf_token() }}">

    <script src="{{ asset('assets/js/vanillaEmojiPicker.js') }}"></script>
    @vite(['resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <title>WhatsApp</title>
    <style>
        .hide {
            display: none;
        }
    </style>

</head>

<body>
    <div class="sidebar">
        <div class="header">
            <div class="avatar">
                <img src="{{ asset('assets/images/avatar.png') }}" alt="User Avatar">
            </div>
            <div class="chat-header-right">
                <img src="{{ asset('assets/images/circle-notch-solid.svg') }}" alt="More Options">
                <img src="{{ asset('assets/images/chat.svg') }}" alt="Chat">
                <button class="three-dot-menu" aria-label="More Options">
                    <img src="{{ asset('assets/images/more.svg') }}" alt="Options">
                </button>
            </div>
            <div id="menu" class="dropdown-menu" style="display: none;">
                <button class="menu-item">Settings</button>
                <button class="menu-item">Clear Chat</button>
                <button class="menu-item">Help</button>
                <button class="menu-item">Logout</button>
            </div>
        </div>

        <div class="sidebar-search">
            <div class="sidebar-search-container">
                <img src="{{ asset('assets/images/search-solid.svg') }}" alt="Search">
                <input type="text" placeholder="Search or start new chat">
            </div>
        </div>
        <div class="sidebar-chats"></div>
    </div>



    <div class="message-container">
        <div class="no-chat-selected">
            <h2>Select a Chat to Begin</h2>
            <p>Start by selecting a user from the sidebar to chat.</p>

        </div>
        <div class="chat-view">
            <div class="header">
                <div class="chat-title">
                    <div class="avatar">
                        <img src="{{ asset('assets/images/avatar.png') }}" alt="User Avatar">
                    </div>
                    <div class="message-header-content">
                        <h4 class="selected_user_name"></h4>
                        <p class="user-status"></p>
                    </div>
                </div>
                <div class="chat-header-right">
                    <img src="{{ asset('assets/images/search-solid.svg') }}" alt="Search">
                    <img src="{{ asset('assets/images/search-solid.svg') }}" alt="Search">
                    <img src="{{ asset('assets/images/more.svg') }}" alt="More Options">
                </div>
            </div>
            <div class="message-content" style="background-image: url('{{ asset('assets/images/background.png') }}');">
            </div>

            <div id="MessageForm" method="POST" class="message-footer messageForm">
                <img id="emojiButton" src="{{ asset('assets/images/smile.svg') }}" alt="Emoji">
                <img src="{{ asset('assets/images/paper-clip.svg') }}" id="fileButton" alt="Attach">
                <input type="file" style="display: none" name="file" id="fileInput">
                <textarea id="messageInput" name="" cols="30" rows="10"></textarea>

                <div class="controls_container">
                    <button class="delete-recording hide">
                        <img src="{{ asset('assets/images/icons/delete.svg') }}" alt="Delete">
                    </button>

                    <audio class="recording-preview hide" controls
                        controlsList="nodownload noplaybackrate novolume"></audio>

                    <button class="start-recording hide">
                        <img src="{{ asset('assets/images/icons/mic.svg') }}" alt="Record">
                    </button>
                    <button class="stop-recording hide">
                        <img src="{{ asset('assets/images/icons/stop.svg') }}" alt="Stop">
                    </button>
                    <button class="resume-recording hide">
                        <img src="{{ asset('assets/images/icons/resume.svg') }}" alt="Resume">
                    </button>
                    <button class="send_btn hide">
                        <img src="{{ asset('assets/images/icons/send_2.svg') }}" alt="Send">
                    </button>

                </div>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            toggleButton();

            $('#messageInput').on('input', function() {
                toggleButton();
            });

            function toggleButton() {
                if ($('#messageInput').val().trim() === "") {
                    $('.start-recording').show();
                    $('.send_btn').hide();
                } else {
                    $('.start-recording').hide();
                    $('.send_btn').show();
                }
            }

            let selectedUserId = null;
            const loginUserId = {{ Auth::user()->id }};
            const currentTime = moment().toISOString();
            let typingTimeout;
            let isFetchingMessages = false;
            let lastScrollHeight = 0;
            let activeUsers = [];
            const unreadMessages = JSON.parse(localStorage.getItem('unreadMessages')) || {};

            window.openChat = function openChat(id) {
                selectedUserId = id;

                let unreadMessages = JSON.parse(localStorage.getItem('unreadMessages')) || {};
                unreadMessages[selectedUserId] = 0;
                localStorage.setItem('unreadMessages', JSON.stringify(unreadMessages));

                const userChat = $('.user-id-' + selectedUserId);
                const badge = userChat.find('.unread-badge');
                badge.hide();

                page = 1;
                fetchMessages(id, page)
                    .then(({
                        selectedUser,
                        messages
                    }) => {
                        if (selectedUser) {
                            $('.message-content').empty();
                            $('.selected_user_name').text(selectedUser.name);
                            updateSelectedUserStatus(selectedUser);
                            displayMessages(messages.reverse(), false);
                            scrollToBottom();

                            $('.no-chat-selected').hide();
                            $('.chat-view').show();
                        }
                    })
                    .catch(error => console.error('Error fetching chat:', error));
            };

            window.Echo.private(`message.${loginUserId}`)
                .listen('MessageSent', (e) => {

                    markMessageAsDelivered(e.message_id);

                    if (selectedUserId == e.sender_id) {
                        displayMessages(e);
                        scrollToBottom();
                    } else {
                        incrementUnreadCount(e.sender_id);
                    }

                    updateMessageInSidebar(e.receiver_id, e.sender_id, e.message, e.last_message_time, "text");
                })
                .listenForWhisper('typing', showTypingIndicator)
                .listenForWhisper('recording', showRecordingIndicator)
                .listen('MessageDelivered', (event) => {
                    try {
                        updateMessageStatus(event.message_id, 'delivered');
                    } catch (error) {
                        console.error('Error handling MessageDelivered event:', error);
                    }
                })
                .listen('MessageRead', (event) => {
                    try {
                        updateMessageStatus(event.message_id, 'read');
                    } catch (error) {
                        console.error('Error handling MessageRead event:', error);
                    }
                });

            function incrementUnreadCount(senderId) {
                let unreadMessages = JSON.parse(localStorage.getItem('unreadMessages')) || {};

                if (!unreadMessages[senderId]) {
                    unreadMessages[senderId] = 0;
                }
                unreadMessages[senderId]++;

                localStorage.setItem('unreadMessages', JSON.stringify(unreadMessages));

                updateUnreadBadge(senderId, unreadMessages[senderId]);
            }

            function updateUnreadBadge(senderId, count) {

                const userChat = $('.user-id-' + senderId);
                const badge = userChat.find('.unread-badge');

                if (badge.length > 0) {
                    const displayCount = count > 99 ? '99+' : count;
                    if (count > 0) {
                        badge.text(displayCount).show();
                    } else {
                        badge.hide();
                    }
                }
            }

            const channel = window.Echo.join('chat-room')
                .here(updateActiveUsers)
                .joining(handleUserJoining)
                .leaving(handleUserLeaving);

            function updateActiveUsers(users) {
                activeUsers = users.filter(user => user.id !== loginUserId);
                fetchAllUsers(activeUsers);
            }

            function handleUserJoining(user) {
                activeUsers.push(user);
                fetchAllUsers(activeUsers);
                updateSelectedUserStatus(user);
            }

            function handleUserLeaving(user) {
                activeUsers = activeUsers.filter(u => u.id !== user.id);
                fetchAllUsers(activeUsers);
                updateLastSeen(user.id);
                if (user.id === selectedUserId) {
                    updateSelectedUserStatus(user);
                }
            }

            function updateSelectedUserStatus(user) {
                if (!selectedUserId) return;
                const isOnline = activeUsers.some(u => u.id === selectedUserId);
                const statusText = isOnline ? 'Online' : `Last seen: ${moment(user.last_seen).fromNow()}`;
                $('.user-status').text(statusText);
            }

            function fetchAllUsers(onlineUsers) {
                $.ajax({
                    url: '/users',
                    method: 'GET',
                    dataType: 'json',
                    success: (users) => renderUsers(users, onlineUsers),
                });
            }

            function renderUsers(users, onlineUsers) {
                let usersHTML = '';
                const unreadMessages = JSON.parse(localStorage.getItem('unreadMessages')) || {};

                users.forEach(user => {
                    const isOnline = onlineUsers.some(u => u.id === user.id);
                    const statusClass = isOnline ? 'online' : 'offline';
                    const lastMessage = user.last_message || '';
                    const createdAt = user.last_message_time || '';
                    const type = user.last_message_type || '';
                    const duration = user.last_message_duration || '';
                    const lastSeen = !isOnline ? `Last seen: ${moment(user.last_seen).fromNow()}` :
                        'Online';

                    usersHTML += `<div class="sidebar-chat user-id-${user.id}" onclick="openChat(${user.id})">
                        <span class="status-indicator ${statusClass}"></span>
                        <div class="chat-avatar">
                            <img title="${lastSeen}" src="${user.avatar_url || '{{ asset('assets/images/avatar.png') }}'}" alt="Avatar">
                        </div>
                        <div class="chat-info">
                            <h4 class="user_name">${user.name}</h4>
                            <p class="message">`;
                    if (type === 'voice') {
                        usersHTML +=
                            `<img src="{{ asset('assets/images/icons/mic.svg') }}" alt="Voice" class="voice-icon" /> ${duration}`;
                    } else {
                        usersHTML += lastMessage;
                    }
                    usersHTML += `</p>
                    </div>
                    <div class="time">
                        <p>${createdAt}</p>
                    </div>`;

                    if (unreadMessages[user.id]) {
                        const unreadCount = unreadMessages[user.id] > 99 ? '99+' : unreadMessages[user.id];
                        usersHTML +=
                            `<div class="unread-badge" style="display: block;">${unreadCount}</div>`;
                    } else {
                        usersHTML += `<div class="unread-badge" style="display: none;"></div>`;
                    }

                    usersHTML += `</div>`;
                });

                $('.sidebar-chats').html(usersHTML);
            }

            function fetchMessages(userId, page = 1) {
                isFetchingMessages = true;
                return $.ajax({
                    url: `/chat/${userId}?page=${page}`,
                    method: 'GET',
                    dataType: 'json',
                }).always(() => {
                    isFetchingMessages = false;
                });
            }


            function updateMessageInSidebar(receiver_id, sender_id, message, message_time, type, voiceDuration =
                null) {
                console.log('====================================');
                console.log(voiceDuration, "voiceDuration");
                console.log('====================================');
                var receiverDiv = $('.user-id-' + receiver_id);
                var senderDiv = $('.user-id-' + sender_id);

                if (receiverDiv.length) {
                    if (type === "voice" && voiceDuration) {
                        receiverDiv.find('.message').html(
                            `<img src="{{ asset('assets/images/icons/mic.svg') }}" alt="Voice" class="voice-icon" />${voiceDuration}`
                        );
                    } else {
                        receiverDiv.find('.message').text(message);
                    }

                    receiverDiv.find('.time p').text(message_time);
                }

                if (senderDiv.length) {
                    if (type === "voice" && voiceDuration) {
                        if (senderDiv.length) {
                            senderDiv.find('.message').html(
                                `<img src="{{ asset('assets/images/icons/mic.svg') }}" alt="Voice" class="voice-icon" />${voiceDuration}`
                            );

                        } else {
                            senderDiv.find('.message').text(message);
                        }
                        senderDiv.find('.time p').text(message_time);
                    }
                }
            }


            function displayMessages(messages, prepend = false) {

                if (!Array.isArray(messages)) {
                    messages = [messages];
                }
                let messageHTML = '';
                messages.forEach(message => {

                    if (message.read_at == null && message.sender_id !== loginUserId) {
                        markMessagesAsRead(message.message_id);
                    }

                    if ($('.typing-indicator').parent().length) {
                        $('.typing-indicator').parent().remove();
                    }

                    const senderClass = message.sender_id === loginUserId ? "chat-sent" : "chat-received";
                    let receipt = message.read_at ? "✓✓" : (message.delivered_at ? "✓✓" : "✓");
                    let receiptClass = message.read_at ? "read" : (message.delivered_at ? "delivered" :
                        "send");

                    if (message.type === 'voice') {
                        messageHTML += `<p data-message-id="${message.message_id}" class="chat-message ${senderClass}">
                                <audio controls>
                                    <source src="${message.message}" type="audio/wav">
                                </audio>
                                <span class="chat-timestamp">${message.created_at}</span>
                                ${senderClass === 'chat-sent' ? `<span class="message-status ${receiptClass}">${receipt}</span>` : ''}
                            </p>`;
                    } else {
                        messageHTML += `<p data-message-id="${message.message_id}" class="chat-message ${senderClass}">
                                ${message.message}
                                <span class="chat-timestamp">${message.created_at}</span>
                                ${senderClass === 'chat-sent' ? `<span class="message-status ${receiptClass}">${receipt}</span>` : ''}
                            </p>`;
                    }
                });

                const $messageContent = $('.message-content');

                if (prepend) {
                    lastScrollHeight = $messageContent[0].scrollHeight;
                    $messageContent.prepend(messageHTML);
                    const currentScrollHeight = $messageContent[0].scrollHeight;
                    const scrollDiff = currentScrollHeight - lastScrollHeight;
                    $messageContent.scrollTop(scrollDiff);
                } else {
                    $messageContent.append(messageHTML);
                    scrollToBottom();
                }
            }


            function scrollToBottom() {
                $('.message-content').scrollTop($('.message-content')[0].scrollHeight);
            }

            let currentPage = 1;

            $('.message-content').on('scroll', function() {
                if ($(this).scrollTop() === 0 && !isFetchingMessages) {
                    currentPage++;
                    fetchMessages(selectedUserId, currentPage)
                        .then(({
                            messages
                        }) => {
                            displayMessages(messages.reverse(), true);
                        })
                        .catch(error => console.error('Error fetching more messages:', error));
                }
            });

            function showTypingIndicator() {
                if ($('.typing-indicator').length === 0) {
                    const typingIndicatorHtml = `
                        <p class="chat-message chat-received">
                            <span class="typing-indicator">
                                <span></span><span></span><span></span>
                            </span>
                        </p>`;
                    $('.message-content').append(typingIndicatorHtml);
                    scrollToBottom();
                }

                $('.typing-indicator').show();
                $('.typing-indicator span').each((index, element) => {
                    $(element).css('animation-delay', `${index * 0.3}s`);
                });

                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    $('.typing-indicator').parent().remove();
                }, 1000);
            }

            function showRecordingIndicator(e) {
                if (e.recording) {
                    if ($('.recording-indicator').length === 0) {
                        const recordingIndicatorHtml = `
                <p class="chat-message chat-received">
                    <span class="recording-indicator">🎤 Recording...</span>
                </p>`;
                        $('.message-content').append(recordingIndicatorHtml);
                        scrollToBottom();
                    }
                } else {
                    $('.recording-indicator').closest('p.chat-message').remove();
                }
            }


            $('#messageInput').on('input', function() {
                if (!selectedUserId) return;
                window.Echo.private(`message.${selectedUserId}`).whisper('typing', {
                    sender_id: loginUserId
                });
            });

            function markMessageAsDelivered(messageId) {
                $.ajax({
                    url: "{{ route('message.mark') }}",
                    method: 'POST',
                    data: {
                        messageId: messageId,
                    },
                    success: function(response) {},
                });
            }

            function markMessagesAsRead(messageId) {
                $.ajax({
                    url: "{{ route('message.mark.read') }}",
                    method: 'POST',
                    data: {
                        messageId: messageId,
                    },
                    success: function(response) {},
                });
            }

            function updateLastSeen(userId) {
                $.ajax({
                    url: `/users/${userId}/update-last-seen`,
                    method: 'POST',
                    success: () => console.log(`Last seen updated for user ${userId}`),
                });
            }

            $('#fileButton').on('click', function() {
                $('#fileInput').click();
            });

            $('#fileInput').on('change', function(event) {
                const file = event.target.files[0];
            });

            $('.three-dot-menu').on('click', function() {
                $('#menu').toggle();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.three-dot-menu, #menu').length) {
                    $('#menu').hide();
                }
            });

            new EmojiPicker({
                trigger: [{
                    selector: '#emojiButton',
                    insertInto: '#messageInput'
                }],
                closeButton: true,
                closeOnSelect: true,
                specialButtons: 'green',
            });

            function updateMessageStatus(messageId, status) {
                const messageElement = document.querySelector(`p[data-message-id="${messageId}"]`);
                let statusSpan = messageElement.querySelector('.message-status');
                const receipt = (status === 'read' || status === 'delivered') ? '✓✓' : '✓';
                statusSpan.textContent = receipt;

                statusSpan.classList.remove('delivered', 'read');
                if (status === 'delivered') {
                    statusSpan.classList.add('delivered');
                } else if (status === 'read') {
                    statusSpan.classList.add('read');
                }
            }
            let isRecording = false;
            let mediaRecorder;
            let audioChunks = [];
            let audioBlob = null;
            let isPaused = false;
            let recordingTime = 0;

            $('.start-recording').on('click', function(e) {
                e.preventDefault();
                if (isRecording) return;
                startRecording();
                $('.start-recording').hide();
                $('.stop-recording').show();
                $('.delete-recording').show();
                $('.send_btn').show();
                $('.resume-recording').hide();
            });

            $('.stop-recording').on('click', function(e) {
                e.preventDefault();
                stopRecording();
                $('.stop-recording').hide();
                $('.start-recording').hide();
                $('.resume-recording').show();
                $('.send_btn').show();
                $('.recording-preview').show();
            });

            $('.resume-recording').on('click', function(e) {
                e.preventDefault();
                resumeRecording();
                $('.resume-recording').hide();
                $('.stop-recording').show();
            });

            $('.delete-recording').on('click', function(e) {
                e.preventDefault();
                deleteRecording();
                $('.delete-recording, .resume-recording, .stop-recording, .send_btn').hide();
                $('.start-recording').show();
            });

            $('.send_btn').on('click', function(e) {
                e.preventDefault();
                $('.submit_btn').prop('disabled', true);

                let messageType = "";
                let messageData = null;

                let messageText = $('#messageInput').val();
                let imageFile = $('#fileInput')[0].files[0];

                if (messageText && !imageFile && !audioChunks.length) {
                    messageType = "text";
                    messageData = messageText;
                } else if (imageFile && !messageText && !audioChunks.length) {
                    messageType = "image";
                    messageData = imageFile;
                } else if (audioChunks.length > 0 || audioBlob) {
                    messageType = "voice";
                    messageData = audioBlob;
                }

                let formData = new FormData();
                formData.append('receiver_id', selectedUserId);
                formData.append('type', messageType);
                formData.append('message', messageData);

                if (imageFile) {
                    formData.append('file', imageFile);
                }

                if (isRecording) {
                    stopRecording(function(audioBlob, duration) {

                        let voiceFormData = new FormData();
                        voiceFormData.append('message', audioBlob);
                        voiceFormData.append('voice_duration', duration);
                        voiceFormData.append('type', 'voice');
                        voiceFormData.append('receiver_id', selectedUserId);

                        sendMessage(voiceFormData);
                    });
                } else {
                    sendMessage(formData);
                }
            });

            function startRecording() {
                isRecording = true;
                isPaused = false;
                audioChunks = [];
                recordingTime = 0;

                navigator.mediaDevices.getUserMedia({
                        audio: true
                    })
                    .then(stream => {
                        mediaRecorder = new MediaRecorder(stream);
                        mediaRecorder.ondataavailable = event => {
                            audioChunks.push(event.data);
                        };
                        mediaRecorder.start();
                    })
                    .catch(err => {
                        console.error('Error accessing microphone:', err);
                        alert('Microphone access denied!');
                    });

                window.Echo.private(`message.${selectedUserId}`).whisper('recording', {
                    sender_id: loginUserId,
                    recording: true
                });
            }

            function stopRecording(callback) {
                isRecording = false;
                isPaused = true;

                if (mediaRecorder) {
                    // Define the onstop event **before** stopping the mediaRecorder
                    mediaRecorder.onstop = () => {
                        if (audioChunks.length === 0) {
                            console.error("No audio data recorded.");
                            if (callback && typeof callback === "function") {
                                callback(null, "00:00"); // Avoid sending null values
                            }
                            return;
                        }

                        audioBlob = new Blob(audioChunks, {
                            type: "audio/wav"
                        });

                        const reader = new FileReader();
                        reader.onloadend = function() {
                            let audioContext;
                            try {
                                audioContext = new(window.AudioContext || window.webkitAudioContext)();
                            } catch (e) {
                                console.error("AudioContext is not supported in this browser.");
                                if (callback && typeof callback === "function") {
                                    callback(audioBlob, "00:00");
                                }
                                return;
                            }

                            audioContext.decodeAudioData(reader.result)
                                .then(buffer => {
                                    const duration = buffer.duration;
                                    const formattedDuration = formatDuration(duration);
                                    console.log("Decoded duration:", formattedDuration);

                                    if (callback && typeof callback === "function") {
                                        callback(audioBlob, formattedDuration);
                                    }
                                })
                                .catch(error => {
                                    console.error("Error decoding audio data:", error);
                                    if (callback && typeof callback === "function") {
                                        callback(audioBlob, "00:00");
                                    }
                                });
                        };

                        reader.readAsArrayBuffer(audioBlob);
                    };

                    mediaRecorder.stop();
                    
                }

                if (mediaRecorder && mediaRecorder.stream) {
                    mediaRecorder.stream.getTracks().forEach(track => track.stop());
                }

                window.Echo.private(`message.${selectedUserId}`).whisper("recording", {
                    sender_id: loginUserId,
                    recording: false
                });
            }


            function formatDuration(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = Math.floor(seconds % 60);
                return `${minutes < 10 ? '0' : ''}${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
            }

            function resumeRecording() {
                if (!isRecording && isPaused) {
                    startRecording();
                }
            }

            function deleteRecording() {
                isRecording = false;
                isPaused = false;
                audioChunks = [];
                audioBlob = null;

                if (mediaRecorder && mediaRecorder.stream) {
                    mediaRecorder.stream.getTracks().forEach(track => track.stop());
                }

                $('.recording-preview').hide().attr('src', '');
                $('.start-recording').show();
                $('.send_btn, .stop-recording, .resume-recording, .delete-recording').hide();
            }

            function sendMessage(formData) {
                $.ajax({
                    url: "{{ route('messages.send') }}",
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(r) {
                        $('#messageInput').val('');
                        $('.recording-preview').hide();
                        displayMessages(r.message);
                        scrollToBottom();
                        toggleButton();
                        updateMessageInSidebar(r.message.receiver_id, r.message.sender_id,
                            r.message.message, r.message.created_at, r.message.type,
                            r.message.voice_duration);
                        $('.submit_btn').prop('disabled', false);
                    },
                    error: function(xhr) {
                        console.error('Error sending message:', xhr.responseText);
                        alert('Error sending message. Please try again.');
                    }
                });

                $('.send_btn').hide();
                $('.delete-recording').hide();
                $('.start-recording').show();
                $('.resume-recording').hide();
                $('.stop-recording').hide();
            }
        });
    </script>
</body>

</html>
