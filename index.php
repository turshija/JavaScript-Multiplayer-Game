<?php
$ip = "localhost";  // 78.47.154.41 // server
$port = "8080";

?><!DOCTYPE HTML>
<html>
    <head>
        <script src="http://<?php echo $ip . ":" . $port; ?>/socket.io/socket.io.js"></script>
        <script type="text/javascript">
            // Paul Irish teh pro
            // http://paulirish.com/2011/requestanimationframe-for-smart-animating/
            window.requestAnimFrame = (function(callback) {
                return window.requestAnimationFrame ||
                window.webkitRequestAnimationFrame ||
                window.mozRequestAnimationFrame ||
                window.oRequestAnimationFrame ||
                window.msRequestAnimationFrame ||
                function(callback){
                    window.setTimeout(callback, 1000 / 60);
                };
            })();
            
            var keyboard = [],
                socket,
                currentPlayer,
                started = false,    // Oznacava da li je u animacija u toku
                frameCounter = 0,   // brojac
                frameRate = 3,      // Na koliko frejmova salje podatke serveru, sto manje to brze
                lagSmoothLvl = 5,   // Smooth umesto seckanja, 1-nema smooth, secka jako, 5 default
                moveSpeed = 100;    // pixela u sekundi
            
            var players = {};
            
            var ball = {
                x: 100,
                y: 50,
                velX: 200,
                velY: 200,
                size: 20
            }

            function animate(lastTime) {
                var canvas = document.getElementById("myCanvas");
                var context = canvas.getContext("2d");
             
                // update
                var date = new Date();
                var time = date.getTime();
                var deltaTime = time - lastTime;
                lastTime = time;
                frameCounter++;
                
                getP1Input(canvas, players[currentPlayer], deltaTime);
                movePlayers();
                moveBall(canvas, ball, deltaTime);
                
                // Svakih X frejmova se serveru salje trenutni igrac
                if (frameCounter % frameRate == 0) {
                    sendPlayerCoords(currentPlayer, players[currentPlayer]);
                    // Pingujemo server, i posle toga server salje responce sa prosledjenim
                    // vremenom nazad, na osnovu cega mozemo da izracunamo ping klijenta
                    socket.emit('ping', time);
                }

                // clear
                context.clearRect(0, 0, canvas.width, canvas.height);
             
                // draw
                context.beginPath();
                
                drawBall(context, ball);   
                drawPaddle(context, players['player1']);
                drawPaddle(context, players['player2']);
               
                

                // request new frame
                requestAnimFrame(function() {
                    animate(lastTime);
                });
            }

            // Funkcija se poziva svakih X frejmova (frameRate), iz igraca izvadi samo 
            // Y koordinatu i brzinu po Y i posalje (da ne salje ceo niz)
            function sendPlayerCoords(playerName, player) {
                var pp = {
                    y: player.y,
                    velY: player.velY
                }
                socket.emit('sendPlayer', currentPlayer, pp);
            }

            // Funkcija koja pokrece loptu na osnovu brzine, i odbija od ivica mape
            // TODO
            function moveBall(canvas, ball, deltaTime) {
                var linearDistX = ball.velX * deltaTime / 1000,
                    linearDistY = ball.velY * deltaTime / 1000;
                

                ball.x += linearDistX;
                ball.y += linearDistY;

                if (ball.y + ball.size > canvas.height) {
                    ball.velY *= -1;
                    ball.y = canvas.height - ball.size - 10;
                }
                if (ball.y - ball.size < 0) {
                    ball.velY *= -1;
                    ball.y = ball.size + 10;
                }
                if (ball.x - ball.size < 0) {
                    ball.velX *= -1;
                    ball.x = ball.size + 10;
                }
                if (ball.x + ball.size > canvas.width) {
                    ball.velX *= -1;
                    ball.x = canvas.width - ball.size - 10;
                }
            }
            
            // Na osnovu korisnikovog inputa podesava brzinu igraca
            function getP1Input(canvas, player, deltaTime) {
                var deltaSpeed = player.speed * deltaTime / 1000;
                // TODO
                // canvas.width, height provera

                if (keyboard['up']) {
                    player.velY = -deltaSpeed;
                } else if (keyboard['down']) {
                    player.velY = deltaSpeed;
                } else {
                    player.velY = 0;
                }
            }
            
            // Funkcija pomera igrace na osnovu trenutne brzine
            // Ukoliko je trenutni igrac, onda ga pomeri na nove dimenzije
            // ali ukoliko je protivnicki igrac, onda pomera polako (smooth)
            // zato sto koordinate protivnickog igraca dobija svakih X frejmova
            // (frameRate), pa da ne bi seckao protivnicki igrac kad se krece
            function movePlayers() {
                for (i in players) {
                    if (i == currentPlayer) {
                        if (players[i].velY != 0) {
                            players[i].y += players[i].velY;
                        }
                    } else {
                        var curY = players[i].y,
                            newY = players[i].newY;
                        
                        players[i].y += (newY - curY) / lagSmoothLvl;
                    }
                }
            }
            
            // Klasicno crtanje "palice" na osnovu podataka iz prosledjenog igraca
            function drawPaddle(context, player) {
                context.rect(player.x, player.y, player.width, player.height);
                context.fillStyle = "#8ED6FF";
                context.fill();
                context.lineWidth = player.borderWidth;
                context.strokeStyle = "black";
                context.stroke();
            }
            
            // Crtanje lopte na osnovu podataka iz prosledjenog niza
            function drawBall(context, ball) {
                context.arc(ball.x, ball.y, ball.size, 0, 2 * Math.PI, false);
                context.fillStyle = "#8ED6FF";
                context.fill();
                context.lineWidth = 5;
                context.strokeStyle = "black";
                context.stroke();
            }
            
            // Salje serveru start game i zapocinje animaciju
            function emitStart() {
                if ( !started ) {
                    socket.emit('startgame');
                }
            }

            window.onload = function() {
                
                // socket = io.connect('http://78.47.154.41:8080');
                socket = io.connect('http://<?php echo $ip . ":" . $port; ?>');
                
                // Salje serveru auth, posle cega dobija svoje mesto na serveru
                // Ako je server pun, obavestava klijenta, inace server poziva 'start'
                socket.on('connect', function(){
                    socket.emit('auth');
                });
                
                // Server javlja da ima slobodnog mesta i javlja klijentu koji
                // je on igrac, kao i poziciju svih igraca i lopte
                socket.on('ready', function(player, allPlayers, ball) {
                    // console.log(player, allPlayers, ball);
                    currentPlayer = player;
                    for (i in allPlayers) {
                        players[i] = allPlayers[i];
                    }
                });
                
                // Server ima svoj frameRate i svakih X frejmova salje novu lokaciju
                // svih igraca i lopte
                // Za trenutnog igraca ne updatuje nista (preskace ga), a za protivnika
                // cuva X i Y koordinate u newX i newY, pa protivnik animira ka tim koordinatama
                socket.on('updateObjects', function(allPlayers, serverBall) {
                    ball = serverBall;

                    for (i in allPlayers) {
                        if (i == currentPlayer) continue;

                        players[i].newX = allPlayers[i].x;
                        players[i].newY = allPlayers[i].y;
                        // players[i] = allPlayers[i];
                    }
                });
                
                // Kad klijent emituje ping, server vraca ping sa klijentovim prosledjenim vremenom
                // Na osnovu toga znamo koliko je bilo potrebno serveru da uzvrati na klijentov
                // emit, jednostavno oduzmemo od trenutnog vremena prosledjeno, podelimo sa dva
                // i dobijamo ping klijenta u ms
                socket.on('ping', function(lastTime) {
                    var date = new Date();
                    var time = date.getTime();
                    
                    ping = (time-lastTime)/2;
                    // console.log("PING: " + ping );
                    document.getElementById('ping').innerHTML = ping;
                });
                
                // Server javlja kada treba pokrenuti igru i startuje se animacija
                socket.on('startgame', function() {
                    var date = new Date();
                    var time = date.getTime();
                    started = true;
                    animate(time);
                });

                // Ovo se poziva uglavnom ako server posalje alert da je server pun
                // i da ne moze da registruje igraca
                socket.on('alert', function(msg) {
                    alert(msg);
                });
            };

            

            window.onkeydown = function(e) {
                if (e.keyCode == 39) keyboard['right'] = true;
                else if (e.keyCode == 37) keyboard['left'] = true;
                if (e.keyCode == 38) keyboard['up'] = true;
                else if (e.keyCode == 40) keyboard['down'] = true;
            };

            window.onkeyup = function(e) {
                if (e.keyCode == 39) keyboard['right'] = false;
                else if (e.keyCode == 37) keyboard['left'] = false;
                if (e.keyCode == 38) keyboard['up'] = false;
                else if (e.keyCode == 40) keyboard['down'] = false;
            };

            

 
        </script>
    </head>
    <body>
        <canvas id="myCanvas" width="940" height="300" style="border:1px solid #000000;">
        </canvas>
        <br />
        Ping: <span id="ping">999</span>ms
        <br />
        <input type="button" value="Start Game" onclick="emitStart()" />
    </body>
</html>