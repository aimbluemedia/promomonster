"""A deliberately literal SMTP server, for testing App\\Support\\Smtp against.

Started by tests/smtp-test.php; not useful on its own. It exists because the
ways an SMTP client goes wrong -- desynchronising on a multi-line reply, losing
a line that starts with a full stop, emitting a bare LF -- are all things you
can only see from the far end of a real socket.

Deliberately awkward in two ways that mirror real servers: every reply is
multi-line, and AUTH is only advertised once TLS is up.

  argv: <port> <ssl|starttls> <output-json>
  env:  SMTP_TEST_CERT, SMTP_TEST_KEY
"""
import base64, socket, ssl, sys, threading, os, json

HOST, PORT, MODE = '127.0.0.1', int(sys.argv[1]), sys.argv[2]   # MODE: ssl | starttls
OUT = sys.argv[3]
USER, PASS = 'logins@promomonster.test', 'mailbox-secret'

ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
ctx.load_cert_chain(os.environ['SMTP_TEST_CERT'], os.environ['SMTP_TEST_KEY'])

def handle(conn):
    got = {'from': None, 'rcpt': [], 'data': None, 'authed': False, 'ehlo': 0}
    f = conn.makefile('rwb')
    def send(s):
        f.write((s + '\r\n').encode()); f.flush()
    def line():
        l = f.readline()
        return l.decode('utf-8', 'replace').rstrip('\r\n') if l else None

    send('220 test.local ESMTP ready')
    while True:
        cmd = line()
        if cmd is None:
            break
        up = cmd.upper()
        if up.startswith('EHLO'):
            got['ehlo'] += 1
            # Multi-line on purpose: a client that reads only the first line
            # desynchronises right here.
            send('250-test.local greets you')
            send('250-SIZE 35882577')
            send('250-8BITMIME')
            if MODE == 'starttls' and not isinstance(conn, ssl.SSLSocket):
                send('250-STARTTLS')
                send('250 HELP')
            else:
                send('250-AUTH LOGIN PLAIN')
                send('250 HELP')
        elif up == 'STARTTLS':
            send('220 Go ahead')
            conn = ctx.wrap_socket(conn, server_side=True)
            f = conn.makefile('rwb')
        elif up == 'AUTH LOGIN':
            send('334 ' + base64.b64encode(b'Username:').decode())
            u = base64.b64decode(line()).decode()
            send('334 ' + base64.b64encode(b'Password:').decode())
            p = base64.b64decode(line()).decode()
            if u == USER and p == PASS:
                got['authed'] = True; send('235 Authentication succeeded')
            else:
                send('535 Authentication credentials invalid')
        elif up.startswith('AUTH PLAIN'):
            raw = base64.b64decode(cmd.split(' ', 2)[2]).decode().split('\0')
            if raw[1] == USER and raw[2] == PASS:
                got['authed'] = True; send('235 Authentication succeeded')
            else:
                send('535 Authentication credentials invalid')
        elif up.startswith('MAIL FROM'):
            if not got['authed']:
                send('530 Authentication required'); continue
            got['from'] = cmd.split('<')[1].split('>')[0]; send('250 OK')
        elif up.startswith('RCPT TO'):
            got['rcpt'].append(cmd.split('<')[1].split('>')[0]); send('250 Accepted')
        elif up == 'DATA':
            send('354 End data with <CR><LF>.<CR><LF>')
            body = []
            while True:
                l = line()
                if l == '.' or l is None:
                    break
                body.append(l)
            got['data'] = '\r\n'.join(body)
            with open(OUT, 'w') as fh:
                json.dump(got, fh)
            send('250 OK queued as ABC123')
        elif up == 'QUIT':
            send('221 Bye'); break
        elif up.startswith('RSET'):
            send('250 OK')
        else:
            send('502 Command not implemented')
    try: conn.close()
    except Exception: pass

s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind((HOST, PORT)); s.listen(5)
print(f'listening {PORT} {MODE}', flush=True)
while True:
    c, _ = s.accept()
    if MODE == 'ssl':
        try: c = ctx.wrap_socket(c, server_side=True)
        except Exception as e:
            print('tls handshake failed:', e, flush=True); continue
    threading.Thread(target=handle, args=(c,), daemon=True).start()
