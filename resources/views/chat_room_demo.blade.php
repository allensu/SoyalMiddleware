<html>
<head>

</head>
<style>
    html, body {
        padding: 0;
        margin: 0;
    }

    #container {
        top: 50px;
        width: 95%;
        margin: 10px;
        display: block;
        position: relative;
    }

    #status-box {
        text-align: right;
        font-size: .6em;
    }

    #content {
        width: 100%;
        height: 350px;
        border: 1px solid darkolivegreen;
        border-radius: 5px;
        overflow: auto;
    }

    #send-box {
        width: 100%;
    }

    #send-box input {
        display: inline-block;
        width: 100%;
    }



    .msg {
        width: 73%;
        display: inline-block;
        padding: 5px 0 5px 10px;
    }

    .msg > span {
        width: 25%;
        display: inline-block;
    }

    .msg > span::before {
        color: darkred;
        content: " { ";
    }

    .msg > span::after {
        color: darkred;
        content: " } ";
    }

    #send-box input.error {
        border: 1px solid red;
    }
</style>

<body>
<div id="container">

    <div id="send-box">
        <form id="send-form">
            <input type="text" name="serverUrl" id="serverUrl" value="ws://127.0.0.1:8080" placeholder="URL">
            <input type="submit" value="connect">
        </form>
        <form id="disconnectForm">
            <input type="submit" value="disconnect">
        </form>
    </div>
    <hr/>


</div>
</body>
<script>

    let _connectForm = document.getElementById("send-form");

    let _ws;

    _connectForm.addEventListener("submit", function (e) {
        e.preventDefault();

        let serverUrl = document.getElementById("serverUrl").value;

        console.log(serverUrl);
        _ws = new WebSocket(serverUrl);

        _ws.onopen = function (e) {
            console.log('connection');

            _ws.send('send a test data');

        }

        _ws.onmessage = function(e) {

            console.log(e);
            //
            // let messageData = JSON.parse(e.data);
            // let event = messageData.event;
            // let status = messageData.status;
            // let message = messageData.message;
            // let data = messageData.data;
            //
            // addMessage(event);
            // addMessage(message);
            // addMessage(JSON.stringify(data));
            //
            // if(event === 'roomList')
            // {
            //     roomList(data);
            // } else if(event === 'roomDetail')
            // {
            //     roomDetail(data);
            // } else if(event === 'createRoomResult')
            // {
            //     createRoomResult(data);
            // } else if(event === 'joinRoomResult')
            // {
            //     joinRoomResult(data);
            // } else if(event === 'leaveRoom')
            // {
            //     leaveRoom(data);
            // } else if(event === 'startGame')
            // {
            //     startGame(data);
            // } else if(event === 'startKick')
            // {
            //     startKick(data);
            // } else if(event === 'kicked')
            // {
            //     kicked(data);
            // } else if(event === 'backRoom')
            // {
            //     backRoom(data);
            // } else if(event === 'searchRoom')
            // {
            //     searchRoom(data);
            // }
        };

        _ws.onclose = function (e) {
            console.log('close');
        }
    });



</script>
</html>