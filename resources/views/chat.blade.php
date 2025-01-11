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

    <style>
        .no-chat-selected {
    text-align: center;
    margin-top: 50px;
    font-family: 'Roboto', sans-serif;
    color: #333;
}

.no-chat-selected h2 {
    font-size: 24px;
    color: #2e2e2e;
    margin-bottom: 20px;
}

.no-chat-selected p {
    font-size: 16px;
    color: #6c6c6c;
    margin-bottom: 30px;
}

    </style>

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
                <button type="submit" class="submit_btn" aria-label="Send Message">
                    <img src="{{ asset('assets/images/send_10.svg') }}" class="send_svg" alt="Send">
                </button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let selectedUserId = null;
            const loginUserId = {{ Auth::user()->id }};
            const currentTime = moment().toISOString();
            let typingTimeout;
            let isFetchingMessages = false;
            let lastScrollHeight = 0;
            let activeUsers = [];
            // localStorage.setItem('lastPageRefreshTime', currentTime);
            const unreadMessages = JSON.parse(localStorage.getItem('unreadMessages')) || {};


            window.Echo.private(`message.${loginUserId}`)
                .listen('MessageSent', (event) => {

                    markMessageAsDelivered(event.message_id);
                    if (selectedUserId == event.receiver_id) {
                        displayMessages(event);
                        scrollToBottom();

                    } else {
                        incrementUnreadCount(event.sender_id);
                    }

                    updateMessageInSidebar(event.receiver_id, event.sender_id, event.message, event
                        .last_message_time);

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
                    error: (error) => console.error('Error fetching users:', error),
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
                    const lastSeen = !isOnline ? `Last seen: ${moment(user.last_seen).fromNow()}` :
                        'Online';

                    usersHTML += `
            <div class="sidebar-chat user-id-${user.id}" onclick="openChat(${user.id})">
                <span class="status-indicator ${statusClass}"></span>
                <div class="chat-avatar">
                    <img title="${lastSeen}" src="${user.avatar_url || '{{ asset('assets/images/avatar.png') }}'}" alt="Avatar">
                </div>
                <div class="chat-info">
                    <h4 class="user_name">${user.name}</h4>
                    <p class="message">${lastMessage}</p>
                </div>
                <div class="time">
                    <p>${createdAt}</p>
                </div>`;

                    if (unreadMessages[user.id]) {
                        const unreadCount = unreadMessages[user.id] > 99 ? '99+' : unreadMessages[user.id];
                        usersHTML +=
                            `<div class="unread-badge" style="display: block;">${unreadCount}</div>`;
                    } else {
                        usersHTML +=
                            `<div class="unread-badge" style="display: none;"></div>`;
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


            function updateMessageInSidebar(receiver_id, sender_id, message, message_time) {
                console.log("aaaa");

                var receiverDiv = $('.user-id-' + receiver_id);
                var senderDiv = $('.user-id-' + sender_id);
                console.log(receiver_id, sender_id, message, message_time);

                if (receiverDiv.length) {
                    receiverDiv.find('.message').text(message);
                    receiverDiv.find('.time p').text(message_time);
                }

                if (senderDiv.length) {
                    senderDiv.find('.message').text(message);
                    senderDiv.find('.time p').text(message_time);
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

                    messageHTML += `<p data-message-id="${message.message_id}" class="chat-message ${senderClass}">
                                        ${message.message}<span class="chat-timestamp">${message.created_at}</span>
                                        ${senderClass === 'chat-sent' ? `<span class="message-status ${receiptClass}">${receipt}</span>` : ''}
                                    </p>`;
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

            $('#MessageForm').on('submit', function(e) {
                e.preventDefault();
                const message = $('#messageInput').val();
                if (!message || !selectedUserId) return;
                $('.submit_btn').prop('disabled', true);
                $('.typing-indicator').parent().remove();

                $.ajax({
                    url: "{{ route('messages.send') }}",
                    method: 'POST',
                    data: {
                        message: message,
                        receiver_id: selectedUserId,
                        _token: '{{ csrf_token() }}',
                    },
                    success: function(response) {
                        console.log(response, "responseresponse");

                        $('#messageInput').val('');
                        displayMessages(response.message);
                        scrollToBottom();
                        updateMessageInSidebar(response.message.receiver_id, response.message
                            .sender_id, response.message.message, response.message
                            .created_at);
                        $('.submit_btn').prop('disabled', false);
                    },
                    error: function(error) {
                        console.error('Error sending message:', error);
                    }
                });
            });

            function markMessageAsDelivered(messageId) {
                $.ajax({
                    url: "{{ route('message.mark') }}",
                    method: 'POST',
                    data: {
                        messageId: messageId,
                        _token: '{{ csrf_token() }}',
                    },
                    success: function(response) {},
                    error: function(error) {
                        console.error('Error sending message:', error);
                    }
                });
            }

            function markMessagesAsRead(messageId) {
                $.ajax({
                    url: "{{ route('message.mark.read') }}",
                    method: 'POST',
                    data: {
                        messageId: messageId,
                        _token: '{{ csrf_token() }}',
                    },
                    success: function(response) {},
                    error: function(error) {
                        console.error('Error sending message:', error);
                    }
                });
            }

            function updateLastSeen(userId) {
                // let lastPageRefreshTime = localStorage.getItem('lastPageRefreshTime');
                // const shouldUpdateLastSeen = !lastPageRefreshTime || moment(currentTime).diff(
                //     lastPageRefreshTime,
                //     'minutes') > 1;
                // if (shouldUpdateLastSeen) {
                $.ajax({
                    url: `/users/${userId}/update-last-seen`,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: () => console.log(`Last seen updated for user ${userId}`),
                    error: (error) => console.error('Error updating last seen:', error),
                });
                // }
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
        });
    </script>
</body>

</html>
