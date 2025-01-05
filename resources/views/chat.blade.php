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
    <style>
        .fg-emoji-container {
            left: 480px !important;
            top: 234px !important;
        }

        .file-button input[type="file"] {
            display: none;
        }

        .dropdown-menu {
            position: absolute;
            top: 50px;
            right: 70.4%;

            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            width: 150px;
            z-index: 2;
        }

        .menu-item {
            padding: 10px;
            background: none;
            border: none;
            cursor: pointer;
        }

        .menu-item:hover {
            background-color: #f0f0f0;
        }

        button {
            border: none !important;
        }

        .read-status {
            font-size: 12px;
            color: #34b7f1;
            margin-left: 5px;
        }

        .read-status {
            color: #34b7f1;
            /* Blue for read receipts */
        }

        .read-status.unread {
            color: #999;
            /* Grey for unread receipts */
        }
    </style>

    <body>
        <!-- Sidebar -->
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

        <!-- Message Container -->
        <div class="message-container">
            <div class="no-chat-selected">
                <h2>No Chat Selected</h2>
                <p>Select a user from the list to start chatting.</p>
                <img src="{{ asset('assets/images/select-user.png') }}" alt="Select User">
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
                        <img src="{{ asset('assets/images/more.svg') }}" alt="More Options">
                    </div>
                </div>
                <div class="message-content"
                    style="background-image: url('{{ asset('assets/images/background.png') }}');"></div>

                <form id="MessageForm" method="POST" class="message-footer messageForm">
                    <img id="emojiButton" src="{{ asset('assets/images/smile.svg') }}" alt="Emoji">



                    <img src="{{ asset('assets/images/paper-clip.svg') }}" id="fileButton" alt="Attach">
                    <input type="file" style="display: none" name="file" id="fileInput">
                    <textarea id="messageInput" name="" id="" cols="30" rows="10"></textarea>
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
                let isFetchingMessages = false; // To prevent duplicate fetches
                let lastScrollHeight = 0;

                let activeUsers = [];
                localStorage.setItem('lastPageRefreshTime', currentTime);

                window.Echo.private(`message.${loginUserId}`)
                    .listen('MessageSent', (event) => {
                        displayMessages(event);
                        scrollToBottom();
                    })
                    .listenForWhisper('typing', showTypingIndicator);

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
                    users.forEach(user => {
                        const isOnline = onlineUsers.some(u => u.id === user.id);
                        const statusClass = isOnline ? 'online' : 'offline';
                        const lastSeen = !isOnline ? `Last seen: ${moment(user.last_seen).fromNow()}` :
                            'Online';
                        usersHTML += `
                            <div class="sidebar-chat" onclick="openChat(${user.id})">
                                <span class="status-indicator ${statusClass}"></span>
                                <div class="chat-avatar">
                                    <img title="${lastSeen}" src="${user.avatar_url || '{{ asset('assets/images/avatar.png') }}'}" alt="Avatar">
                                </div>
                                <div class="chat-info">
                                    <h4>${user.name}</h4>
                                    <p>Last Message</p>
                                </div>
                            </div>`;
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
                                markMessagesAsRead(selectedUser.id);
                                $('.no-chat-selected').hide();
                                $('.chat-view').show();
                            }
                        })
                        .catch(error => console.error('Error fetching chat:', error));
                };

                function displayMessages(messages, prepend = false) {
                    if (!Array.isArray(messages)) {
                        messages = [messages];
                    }


                    let messageHTML = '';
                    messages.forEach(message => {
                        const senderClass = message.sender_id === loginUserId ? "chat-sent" : "chat-received";
                        const readReceipt = message.read_at ? `<span class="read-status">✓✓</span>` :
                            `<span class="read-status">✓</span>`;


                        messageHTML +=
                            `<p class="chat-message ${senderClass}">${message.message}<span class="chat-timestamp">${message.created_at}</span>${senderClass === 'chat-sent' ? readReceipt : ''}</p>`;
                    });
                    const $messageContent = $('.message-content');


                    if (prepend) {
                        lastScrollHeight = $messageContent[0].scrollHeight;
                        $messageContent.prepend(messageHTML);

                        const currentScrollHeight = $messageContent[0].scrollHeight;
                        const scrollDiff = currentScrollHeight - lastScrollHeight;
                        $messageContent.scrollTop(scrollDiff);
                    } else {
                        markMessagesAsRead(selectedUserId);
                        $messageContent.append(messageHTML);
                        scrollToBottom();
                    }
                }



                function scrollToBottom() {
                    $('.message-content').scrollTop($('.message-content')[0].scrollHeight);
                }

                let page = 1;
                $('.message-content').on('scroll', function() {
                    if ($(this).scrollTop() === 0 && !isFetchingMessages) {
                        page++;
                        fetchMessages(selectedUserId, page)
                            .then(({
                                messages
                            }) => {
                                displayMessages(messages.reverse(), true); // Prepend older messages
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
                    }, 3000);
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
                            $('#messageInput').val('');
                            displayMessages(response.message);
                            scrollToBottom();
                            $('.submit_btn').prop('disabled', false);
                        },
                        error: function(error) {
                            console.error('Error sending message:', error);
                        }
                    });
                });

                function updateLastSeen(userId) {
                    let lastPageRefreshTime = localStorage.getItem('lastPageRefreshTime');
                    const shouldUpdateLastSeen = !lastPageRefreshTime || moment(currentTime).diff(
                        lastPageRefreshTime,
                        'minutes') > 1;
                    if (shouldUpdateLastSeen) {
                        $.ajax({
                            url: `/users/${userId}/update-last-seen`,
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: () => console.log(`Last seen updated for user ${userId}`),
                            error: (error) => console.error('Error updating last seen:', error),
                        });
                    }
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
                    }, ],
                    closeButton: true,
                    closeOnSelect: true,
                    specialButtons: 'green',
                });

                function markMessagesAsRead(userId) {
                    $.ajax({
                        url: `/messages/${userId}/read`,
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function() {
                            console.log('Messages marked as read');
                            updateReadReceipts(userId);
                        },
                        error: function(error) {
                            console.error('Error marking messages as read:', error);
                        }
                    });
                }

                function updateReadReceipts(userId) {
                    $('.chat-sent .read-status').each(function() {
                        $(this).html('✓✓');
                    });
                }

            });
        </script>
    </body>

    </html>
