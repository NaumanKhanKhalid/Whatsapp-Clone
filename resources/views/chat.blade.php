    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <link rel="shortcut icon" type="image/jpg" href="{{ asset('assets/images/whatsapp.png') }}">


        @vite(['resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

        {{-- @vite(['resources/css/app.css']) --}}

        <title>WhatsApp</title>
    </head>

    <body>
        <div class="sidebar">
            <div class="header">
                <div class="avatar">
                    <img src="{{ asset('assets/images/avatar.png') }}" alt="">
                </div>
                <div class="chat-header-right">
                    <img src="{{ asset('assets/images/circle-notch-solid.svg') }}" alt="">
                    <img src="{{ asset('assets/images/chat.svg') }}" alt="">
                    <img src="{{ asset('assets/images/more.svg') }}" alt="">
                </div>
            </div>
            <div class="sidebar-search">
                <div class="sidebar-search-container">
                    <img src="{{ asset('assets/images/search-solid.svg') }}" alt="">
                    <input type="text" placeholder="Search or start new chat">
                </div>
            </div>
            <div class="sidebar-chats">
                {{-- @foreach ($users as $user)
                    <div class="sidebar-chat" onclick="openChat({{ $user->id }})">
                        <div class="chat-avatar">
                            <img src="{{ $user->avatar_url ?? asset('assets/images/avatar.png') }}" alt="Avatar">
                        </div>
                        <div class="chat-info">
                            <h4>{{ $user->name }}</h4>
                            <p>{{ $user->lastMessage->message ?? 'No messages yet' }}</p>
                        </div>
                        <div class="time">
                            <p>{{ $user->lastMessage?->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach --}}
            </div>
        </div>

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
                            <img src="{{ asset('assets/images/avatar.png') }}" alt="">
                        </div>
                        <div class="message-header-content">
                            <h4 class="selected_user_name"></h4>
                            <p>online</p>
                        </div>
                    </div>
                    <div class="chat-header-right">
                        <img src="{{ asset('assets/images/search-solid.svg') }}" alt="">
                        <img src="{{ asset('assets/images/more.svg') }}" alt="">
                    </div>
                </div>
                <div class="message-content"
                    style="background-image: url('{{ asset('assets/images/background.png') }}');">
                </div>
                <form id="MessageForm" method="POST" class="message-footer messageForm">
                    <img src="{{ asset('assets/images/smile.svg') }}" alt="">
                    <img src="{{ asset('assets/images/paper-clip.svg') }}" alt="">
                    <input type="text" name="message" id="messageInput" placeholder="Type a message" required>
                    <button type="submit" style="border: none">
                        <img src="{{ asset('assets/images/send.svg') }}" alt="">
                    </button>
                </form>

            </div>
        </div>

        <script>
            let selectedUserId = null;
            var loginUserId = "{{ Auth::user()->id }}";
            function openChat(id) {
                selectedUserId = id;

                $.ajax({
                    url: `/chat/${id}`,
                    method: 'GET',
                    dataType: 'json',
                    success: ({
                        selectedUser,
                        messages
                    }) => {
                        if (selectedUser) {
                            $('.selected_user_name').text(selectedUser.name);
                            $('.message-content').empty();

                            messages.map(displayMessages);

                            scrollToBottom();
                            $('.no-chat-selected').hide();
                            $('.chat-view').show();
                        }
                    },
                    error: (error) => console.error('Error fetching chat:', error)
                });
            }

            function displayMessages(message) {
                const senderClass = message.sender_id === parseInt("{{ Auth::id() }}") ? "chat-sent" : "chat-received";
                const messageHtml = `
                    <p class="chat-message ${senderClass}">
                        ${message.message} 
                        <span class="chat-timestamp">${message.created_at}</span>
                    </p>
                `;
                $('.message-content').append(messageHtml);
            }

            function scrollToBottom() {
                $('.message-content').scrollTop($('.message-content')[0].scrollHeight);
            }

            $('#MessageForm').on('submit', function(e) {
                e.preventDefault();

                const message = $('#messageInput').val().trim();
                if (!message || !selectedUserId) {
                    return;
                }

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
                    },
                    error: function(error) {
                        console.error('Error sending message:', error);
                    }
                });
            });

           
            let activeUsers = [];
            $(document).ready(function() {
                window.Echo.private(`message.${loginUserId}`)
                    .listen('MessageSent', (event) => {
                        console.log(event, "Aaaa");

                        displayMessages(event);
                        scrollToBottom();
                    });


                const channel = window.Echo.join('chat-room')
                    .here((users) => {

                        activeUsers = users;
                        renderUsers(activeUsers);
                        console.log('Current users:', users);
                    })
                    .joining((user) => {
                        activeUsers.push(user);
                        renderUsers(activeUsers);
                        console.log('User joined:', user);
                    })
                    .leaving((user) => {

                        activeUsers = activeUsers.filter(u => u.id !== user.id);
                        renderUsers(activeUsers);
                        console.log('User left:', user);
                    });

                function renderUsers(users) {
                    let usersHTML = '';

                    users = users.filter(user => user.id !== {{ Auth::user()->id }});

                    users.forEach((user) => {
                        const userHTML = `
                        <div class="sidebar-chat" onclick="openChat(${user.id})">
                            <div class="chat-avatar">
                                <img src="${user.avatar_url || '{{ asset('assets/images/avatar.png') }}'}" alt="Avatar">
                            </div>
                            <div class="chat-info">
                                <h4>${user.name}</h4>
                                <p>${user.last_message?.message || 'No messages yet'}</p>
                            </div>
                            <div class="time">
                                <p>${user.last_message?.created_at_human || ''}</p>
                            </div>
                        </div>`;
                        usersHTML += userHTML;
                    });

                    $('.sidebar-chats').html(usersHTML);
                }

            });
        </script>

    </body>

    </html>
