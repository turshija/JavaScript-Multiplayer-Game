var app = require('express').createServer();
var io = require('socket.io').listen(app);

app.listen(8080);

// routing
app.get('/', function (req, res) {
  res.sendfile(__dirname + '/index.html');
});

io.set('log level', 1);

var players = {};
var defaultplayers = {};

defaultplayers['player1'] = {
    x: 10,
    y: 50,
    newX : 10,
    newY : 50,
    velX : 0,
    velY : 0,
    width: 10,
    height: 100,
    borderWidth: 1,
    speed: 400,
    online : false
};

defaultplayers['player2'] = {
    x: 900,
    y: 150,
    newX : 900,
    newY : 150,
    velX : 0,
    velY : 0,
    width: 10,
    height: 100,
    borderWidth: 1,
    speed: 400,
    online : false
};

var defaultball = {
    x: 100,
    y: 50,
    velX: 200,
    velY: 200,
    size: 20
}

players['player1'] = defaultplayers['player1'];
players['player2'] = defaultplayers['player2'];
var ball = defaultball;

var frameCounter = 0,
    frameRate = 5,      // brzina refresha, 1-svaki frejm, 2-svaki drugi, 3-svaki treci ...
    animationOn = false;



io.sockets.on('connection', function (socket) {

    function animate(lastTime) {
        if ( !animationOn ) return;

        var date = new Date();
        var time = date.getTime();
        var deltaTime = time - lastTime;
        lastTime = time;
        frameCounter++;

        if (frameCounter % frameRate == 0) {
            io.sockets.emit('updateObjects', players);
        }


        setTimeout( function() {
            animate(lastTime)
        }, 1000/60 );

    }


    socket.on('auth', function() {
        var i, logged = false;

        for (i in players) {
            if ( !players[i]['online'] ) {
                socket.player = i;
                players[i].online = true;
                logged = true;
                console.log("USPESNO ULOGOVAN " +i);
                break;
            }
        }


        if ( !logged ) {
            console.log("SERVER FULL !");
            socket.emit('alert', 'Server je pun ! :)');
            return;
        }
        socket.emit('ready', i, players, ball);
    });

    socket.on('sendPlayer', function(current, player) {
        for (i in player) {
            players[current][i] = player[i];
        }

        // console.log(players);
    });

    socket.on('disconnect', function() {
        var current = socket.player;
        if (typeof current == "undefined") return;
        if (typeof players[current].online == "undefined") return;

        players[current].online = false;
        animationOn = false;
        console.log("Igrac "+current+" je upravo napustio igru !");
        //console.log(players);
    });

    socket.on('startgame', function() {
        if ( animationOn ) return;

        var playersOnline = 0;
        for (i in players) {
            if (players[i].online) {
                playersOnline++;
            }
        }

        if (playersOnline == 2) {

            var date = new Date();
            var time = date.getTime();
            animationOn = true;

            animate(time);
            io.sockets.emit('startgame');

        } else {
            socket.emit('alert', 'Potreban je jos jedan igrac !');
        }

    });

    socket.on('ping', function(time) {
        socket.emit('ping', time);
    });
});
