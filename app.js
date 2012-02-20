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
    x: 300,
    y: 50,
    velX: 400,
    velY: 0,
    size: 20,
    maxSpeed: 1000
}

function cloneObject(source) {
    for (i in source) {
        if (typeof source[i] == 'source') {
            this[i] = new cloneObject(source[i]);
        }
        else{
            this[i] = source[i];
        }
    }
}

players['player1'] = new cloneObject(defaultplayers['player1']);
players['player2'] = new cloneObject(defaultplayers['player2']);
var ball = new cloneObject(defaultball);

var frameCounter = 0,
    frameRate = 3,      // brzina refresha, 1-svaki frejm, 2-svaki drugi, 3-svaki treci ...
    animationOn = false,
    canvas = {
        width: 940,
        height: 300
    }





io.sockets.on('connection', function (socket) {

    function animate(lastTime) {
        if ( !animationOn ) return;

        var date = new Date();
        var time = date.getTime();
        var deltaTime = time - lastTime;
        lastTime = time;
        frameCounter++;

        moveBall(canvas, ball, deltaTime);

        if (frameCounter % frameRate == 0) {
            io.sockets.emit('updateObjects', players, ball);
        }


        setTimeout( function() {
            animate(lastTime)
        }, 1000/60 );

    }

    function calculateDistance(player, ball) {
        var a = Math.abs( (player.x + player.width/2) - ball.x );
        var b = Math.abs( (player.y + player.height/2) - ball.y );
        return Math.sqrt( Math.pow(a,2) + Math.pow(b,2) );
    }

    function checkCollision(ball, player, collisionXstart, collisionXend) {
        // console.log(ball, player, collisionXstart, collisionXend);

        // if ( (ball.x + ball.size > collisionXstart ) && (ball.x + ball.size < collisionXend) ) {
        if ( (ball.x > collisionXstart ) && (ball.x < collisionXend) ) {
            // console.log("RACUNADEM !");
            var dist = calculateDistance(player, ball);
            
            console.log(dist);

            if ( dist < (player.height/2 + ball.size) ) {
                return true;
            }
        }

        return false;
    }

    function moveBall(canvas, ball, deltaTime) {
        var col = false,
            linearDistX = ball.velX * deltaTime / 1000,
            linearDistY = ball.velY * deltaTime / 1000;
        
        
        ball.x += linearDistX;
        ball.y += linearDistY;
        
        if ( ball.velX > 0 ) {
            col = checkCollision(ball,  players['player2'],     players['player2'].x - ball.size,   players['player2'].x + players['player2'].width - ball.size );

            if (col) {
                ball.velX *= -1;
                ball.x = players['player2'].x - ball.size;
            }
        }

        if ( ball.velX < 0) {
            col = checkCollision(ball,  players['player1'],     players['player1'].x + ball.size,   players['player1'].x + players['player1'].width + ball.size);

            if (col) {
                ball.velX *= -1;
                ball.x = players['player1'].x + ball.size;
            }
        }
        

        if (ball.y + ball.size > canvas.height) {
            ball.velY *= -1;
            ball.y = canvas.height - ball.size - 10;
        }
        if (ball.y - ball.size < 0) {
            ball.velY *= -1;
            ball.y = ball.size + 10;
        }


        if (ball.x - ball.size < 0) {
            // ball.x = 300;
            // console.log(defaultball);
            // ball.x = defaultball.x;
            respawnBall();
        }
        if (ball.x + ball.size > canvas.width) {
            // ball.x = 300;
            // console.log(defaultball);
            // ball.x = defaultball.x;

            respawnBall();
        }
    }

    function respawnBall() {
        ball = new cloneObject( defaultball );
        ball.velX *= -1;
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

            
            ball = new cloneObject(defaultball);

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


