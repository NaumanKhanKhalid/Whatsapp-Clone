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

            <form id="MessageForm" method="POST" class="message-footer messageForm">
                <img id="emojiButton" src="{{ asset('assets/images/smile.svg') }}" alt="Emoji">
                <img src="{{ asset('assets/images/paper-clip.svg') }}" id="fileButton" alt="Attach">
                <input type="file" style="display: none" name="file" id="fileInput">
                <textarea id="messageInput" name="" cols="30" rows="10"></textarea>
                <button type="button" class="submit_btn" aria-label="Send Message">

                    <img src="{{ asset('assets/images/icons/mic.svg') }}" class="mic_svg" alt="Mic Svg">
                    <img src="{{ asset('assets/images/icons/stop.svg') }}" style="display: none;" class="stop_svg"
                        alt="Stop Svg">
                    <img src="{{ asset('assets/images/icons/send_2.svg') }}" class="send_svg" alt="Send Svg">
                </button>
            </form>
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
                    $('.mic_svg').show();
                    $('.send_svg').hide();
                } else {
                    $('.mic_svg').hide();
                    $('.send_svg').show();
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


            window.Echo.private(`message.${loginUserId}`)
                .listen('MessageSent', (e) => {

                    markMessageAsDelivered(e.message_id);
                    if (selectedUserId == e.receiver_id) {
                        displayMessages(e);
                        scrollToBottom();

                    } else {
                        incrementUnreadCount(e.sender_id);
                    }

                    updateMessageInSidebar(e.receiver_id, e.sender_id, e.message, e.last_message_time, "text");

                })


                .listenForWhisper('typing', showTypingIndicator);

            window.Echo.private(`message.${loginUserId}`)
                .listen('MessageDelivered', (event) => {
                    updateMessageStatus(event.message_id, 'delivered');
                });

            window.Echo.private(`message.${loginUserId}`)
                .listen('MessageRead', (event) => {

                    updateMessageStatus(event.message_id, 'read');
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
        const duration = user.last_message_duration || '';  // Get the voice duration if it's a voice message
        const lastSeen = !isOnline ? `Last seen: ${moment(user.last_seen).fromNow()}` : 'Online';

        // Start building the user HTML structure
        usersHTML += `
            <div class="sidebar-chat user-id-${user.id}" onclick="openChat(${user.id})">
                <span class="status-indicator ${statusClass}"></span>
                <div class="chat-avatar">
                    <img title="${lastSeen}" src="${user.avatar_url || '{{ asset('assets/images/avatar.png') }}'}" alt="Avatar">
                </div>
                <div class="chat-info">
                    <h4 class="user_name">${user.name}</h4>
                    <p class="message">
        `;

        // Check the message type and display accordingly
        if (type === 'voice') {
            // If it's a voice message, show the voice icon and duration
            usersHTML += `
                    <div class="voice-message">
                        <img src="{{asset('assets/images/icons/mic.svg')}}" alt="Voice" class="voice-icon" />
                        <span class="voice-duration">${duration}</span>
                    </div>
            `;
        } else {
            // Otherwise, just display the last text message
            usersHTML += lastMessage;
        }

        usersHTML += `
                    </p>
                </div>
                <div class="time">
                    <p>${createdAt}</p>
                </div>
        `;

        // Display unread message badge if there are unread messages for the user
        if (unreadMessages[user.id]) {
            const unreadCount = unreadMessages[user.id] > 99 ? '99+' : unreadMessages[user.id];
            usersHTML += `<div class="unread-badge" style="display: block;">${unreadCount}</div>`;
        } else {
            usersHTML += `<div class="unread-badge" style="display: none;"></div>`;
        }

        usersHTML += `</div>`;
    });

    // Insert the generated HTML into the sidebar
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


            function updateMessageInSidebar(receiver_id, sender_id, message, message_time, type, voiceDuration = null) {
    var receiverDiv = $('.user-id-' + receiver_id);
    var senderDiv = $('.user-id-' + sender_id);

    // Set the message for the receiver
    if (receiverDiv.length) {
        if (type === "voice" && voiceDuration) {
            // If it's a voice message, add the voice icon and duration
            receiverDiv.find('.message').html(`
                <div class="voice-message">
                    <img src="{{asset('assets/images/icons/mic.svg')}}" alt="Voice" class="voice-icon" />
                    <span class="voice-duration">${voiceDuration}</span>
                </div>
            `);
        } else {
            receiverDiv.find('.message').text(message); // Text message
        }
        receiverDiv.find('.time p').text(message_time); // Update message time
    }

    // Set the message for the sender
    if (senderDiv.length) {
        if (type === "voice" && voiceDuration) {
            // If it's a voice message, add the voice icon and duration
            senderDiv.find('.message').html(`
                <div class="voice-message">
                    <img src="{{asset('assets/images/icons/mic.svg')}}" alt="Voice" class="voice-icon" />
                    <span class="voice-duration">${voiceDuration}</span>
                </div>
            `);
        } else {
            senderDiv.find('.message').text(message); // Text message
        }
        senderDiv.find('.time p').text(message_time); // Update message time
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

                    // Handle different message types (text vs voice)
                    if (message.type === 'voice') {
                        // Display voice message with a player
                        messageHTML += `<p data-message-id="${message.message_id}" class="chat-message ${senderClass}">
                                <audio controls>
                                    <source src="${message.message}" type="audio/wav">
                                </audio>
                                <span class="chat-timestamp">${message.created_at}</span>
                                ${senderClass === 'chat-sent' ? `<span class="message-status ${receiptClass}">${receipt}</span>` : ''}
                            </p>`;
                    } else {
                        // Display text message
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

            $('.mic_svg').on('click', function(e) {
                e.preventDefault();
                if (isRecording) return;
                startRecording();
            });

            $('.send_svg').on('click', function(e) {
                e.preventDefault();
                $('.submit_btn').prop('disabled', true);
                $('.typing-indicator').parent().remove();

                var messageType = "";
                var messageData = null;

                var messageText = $('#messageInput').val();
                var imageFile = $('#fileInput')[0].files[0];

                if (messageText && !imageFile && !audioChunks.length) {
                    messageType = "text";
                    messageData = messageText;
                } else if (imageFile && !messageText && !audioChunks.length) {
                    messageType = "image";
                    messageData = imageFile;
                }

                var formData = new FormData();
                formData.append('receiver_id', selectedUserId);
                formData.append('type', messageType);
                formData.append('message', messageData);

                if (imageFile) {
                    formData.append('file', imageFile);
                }

                if (isRecording) {
                    stopRecording(function(audioBlob, duration) {
                        var voiceFormData = new FormData();
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
                audioChunks = [];

                $('.mic_svg').hide();
                $('.send_svg').show();
                $('.stop_svg').show();
                $('.recording-indicator').text('Recording...');

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
                        alert('Microphone access denied! Please check your device settings.');
                        stopRecording(null);
                    });
            }

            function stopRecording(callback) {
    isRecording = false;

    if (mediaRecorder) {
        mediaRecorder.stop();
        mediaRecorder.onstop = () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });

            const reader = new FileReader();
            reader.onloadend = function() {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                audioContext.decodeAudioData(reader.result, function(buffer) {
                    const duration = buffer.duration; // Duration in seconds
                    console.log('Audio Duration:', duration); // Debugging log
                    const formattedDuration = formatDuration(duration);

                    // Pass audioBlob and formatted duration to callback
                    callback(audioBlob, formattedDuration);
                }, function(e) {
                    console.error('Error decoding audio data:', e);
                    callback(audioBlob, '00:00'); // Return default value if error occurs
                });
            };

            reader.readAsArrayBuffer(audioBlob);
        };
    }

    // Reset UI
    $('.mic_svg').show();
    $('.send_svg').hide();
    $('.stop_svg').hide();
    $('.recording-indicator').text(''); // Clear recording status
}


            // Format duration in seconds to HH:MM:SS
            function formatDuration(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = Math.floor(seconds % 60);
                return `${minutes < 10 ? '0' : ''}${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
            }

            function sendMessage(formData) {
                $.ajax({
                    url: "{{ route('messages.send') }}",
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('.send_svg').attr('disabled', true);
                    },
                    success: function(e) {
                        console.log(e, "Eeeeee");

                        $('#messageInput').val('');
                        displayMessages(e.message);
                        scrollToBottom();
                        updateMessageInSidebar(e.message.receiver_id, e.message.sender_id, e.message
                            .message, e.message.created_at, e.message.type, e.message.voice_duration
                            );
                        $('.submit_btn').prop('disabled', false);
                    },
                    error: function(xhr) {
                        console.error('Error sending message:', xhr.eText);
                        alert('Error sending message. Please try again.');
                        $('.send_svg').attr('disabled', false);
                    }
                });
            }
        });
    </script>
</body>

</html>
