#!/usr/bin/env python3
"""Niet-visuele integratietest in een tijdelijke kopie, zonder echte accounts/data."""
import base64
import hashlib
import html
from html.parser import HTMLParser
import http.cookiejar
import json
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

PROJECT = Path(__file__).resolve().parents[1]
checks = []


def check(value, name):
    if not value:
        raise AssertionError(name)
    checks.append(name)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())

    def request(self, path, data=None, files=None):
        headers = {}
        if files:
            boundary = 'immens-' + secrets.token_hex(12)
            parts = []
            for key, value in data.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            for key, (name, mime, content) in files.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode())
            body = b''.join(parts)
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        else:
            body = urllib.parse.urlencode(data).encode() if data is not None else None
        request = urllib.request.Request(self.base + path, data=body, headers=headers)
        try:
            response = self.opener.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.read().decode('utf-8', errors='replace'), response.headers

    def token(self, path):
        code, body, headers = self.request(path)
        match = re.search(r'name="csrf" value="([a-f0-9]+)"', body)
        check(bool(match), 'CSRF beschikbaar: ' + path)
        return match[1]


class Assets(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = set()
        self.h1s = 0
        self.ids = set()
        self.anchors = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'h1':
            self.h1s += 1
        if 'id' in attrs:
            self.ids.add(attrs['id'])
        if tag in ('img', 'script') and 'src' in attrs:
            self.urls.add(attrs['src'])
        if tag == 'link' and attrs.get('rel') in ('stylesheet', 'icon'):
            self.urls.add(attrs['href'])
        if tag == 'a' and attrs.get('href', '').startswith('#'):
            self.anchors.append(attrs['href'][1:])


with tempfile.TemporaryDirectory(prefix='immens-controle-', dir='/private/tmp') as work:
    root = Path(work)
    shutil.copytree(PROJECT / 'public_html', root / 'public_html')
    # Test ook de echte productie-onderhoudsstand; de gewone preview omzeilt die lokaal.
    router = (PROJECT / 'router.php').read_text().replace("define('IMMENS_LOCAL_PREVIEW', true);", '// Productiegedrag voor deze test.')
    (root / 'router.php').write_text(router)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    log_path = root / 'server.log'
    with log_path.open('w') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'public_html', 'router.php'], cwd=root, stdout=log, stderr=log)
        try:
            base = f'http://127.0.0.1:{port}'
            anonymous = Client(base)
            for _ in range(50):
                try:
                    status, body, _ = anonymous.request('/')
                    break
                except urllib.error.URLError:
                    time.sleep(.1)
            check(status == 503, 'Uploadversie begint in onderhoud')
            admin = Client(base)
            code, _, headers = admin.request('/beheer/')
            check(code == 302 and 'login' in headers.get('Location', ''), 'Beheer is afgeschermd')
            password = secrets.token_urlsafe(28)
            token = admin.token('/beheer/wachtwoord-instellen.php')
            code, body, _ = admin.request('/beheer/wachtwoord-instellen.php', {'csrf':token,'gebruikersnaam':'controle','wachtwoord':password,'herhaal':password})
            check('Inloggegevens ingesteld' in body, 'Eerste beheeraccount instellen')
            token = admin.token('/beheer/login.php')
            code, _, headers = admin.request('/beheer/login.php', {'csrf':token,'gebruiker':'controle','wachtwoord':password})
            check(code == 302 and 'index.php' in headers.get('Location', ''), 'Inloggen met eigen account')
            token = admin.token('/beheer/index.php')
            code, _, _ = admin.request('/beheer/onderhoudsmodus.php', {'csrf':token,'onderhoud':'uit'})
            check(code == 302 and json.loads((root/'public_html/data/onderhoud.json').read_text())['actief'] is False, 'Onderhoud uit via dashboard')

            for theme in ('', 'atelier', 'blauwdruk', 'galerie', 'werkplaats', 'privacy'):
                code, body, _ = anonymous.request('/' + theme)
                check(code == 200 and '<html lang="nl">' in body and 'noindex,nofollow' in body, 'Publieke route en noindex: /' + theme)
                check(not re.search(r'(?:Warning|Fatal error|Deprecated):', body), 'Geen PHP-melding: /' + theme)
                parser = Assets(); parser.feed(body)
                check(parser.h1s == 1, 'Eén hoofdkop: /' + theme)
                check(all(anchor in parser.ids for anchor in parser.anchors), 'Ankerbestemmingen aanwezig: /' + theme)
                for url in parser.urls:
                    check(url.startswith('/') and anonymous.request(url)[0] == 200, 'Lokaal asset beschikbaar: ' + url.split('?')[0])
            check(anonymous.request('/bestaat-niet')[0] == 404, 'Onbekende pagina geeft 404')
            check(anonymous.request('/?thema[]=atelier')[0] == 404, 'Ongeldige themainvoer geeft 404')
            for path in ('/data/content.json','/_lib/config.php','/data/geoip/ip2location-DB1LITEBINIPV6.bin','/.htaccess','/themas/atelier.php'):
                check(anonymous.request(path)[0] == 403, 'Gevoelige route geblokkeerd: ' + path)
            check('ip2location.com' in anonymous.request('/privacy')[1], 'Verplichte lokale GeoIP-bronvermelding aanwezig')

            for path in ('paginas.php','kernteksten.php','collectie.php','navigatie.php','seo.php','media.php','gebruikers.php','versies.php','formulieren.php','toegang.php','geoip.php'):
                code, body, _ = admin.request('/beheer/' + path)
                check(code == 200 and not re.search(r'(?:Warning|Fatal error|Deprecated):', body), 'Beheerscherm technisch geldig: ' + path)
            token = admin.token('/beheer/kernteksten.php?pagina=atelier')
            admin.request('/beheer/kernteksten.php?pagina=atelier', {'csrf':token,'_pagina':'atelier','atelier_titel':'Technische controle van de titel'})
            check('Technische controle van de titel' in anonymous.request('/atelier')[1], 'Pagina-inhoud wordt publiek bijgewerkt')
            check(any((root/'public_html/data/versies').rglob('*.json')), 'Automatische herstelversie gemaakt')

            token = admin.token('/beheer/media.php')
            code, body, _ = admin.request('/beheer/media.php', {'csrf':token,'actie':'upload','alt':'Technische uploadcontrole','titel':'Controlefoto'}, {'bestand':('controle.jpg','image/jpeg',(PROJECT/'public_html/assets/img/detail.jpg').read_bytes())})
            check('Afbeelding geüpload.' in body and bool(list((root/'public_html/assets/img/uploads').glob('*.jpg'))), 'Beveiligde afbeeldingupload')
            uploaded = next((root/'public_html/assets/img/uploads').glob('*.jpg'))

            token = admin.token('/beheer/collectie.php?actie=nieuw')
            admin.request('/beheer/collectie.php', {'csrf':token,'actie':'opslaan','titel':'Controleproject','slug':'controle','categorie':'Stucwerk','datum':'2026-11-12','samenvatting':'Projectomschrijving voor de integratietest.','tekst':'<p>Extra projectinformatie.</p>','actief':'1'}, {'afbeelding_upload':('project.jpg','image/jpeg',uploaded.read_bytes())})
            for theme in ('atelier','blauwdruk','galerie','werkplaats'):
                body = anonymous.request('/'+theme)[1]
                check('Controleproject' in body and 'Extra projectinformatie' in body and 'data-project="voorbeeld-' not in body, 'Project vervangt voorbeeldvakken: ' + theme)

            token = admin.token('/beheer/extra-paginas.php?nieuw=1')
            code, body, _ = admin.request('/beheer/extra-paginas.php?nieuw=1', {'csrf':token,'titel':'Projectreportage','slug':'project-controle','intro':'Een reportage.','omschrijving':'Een technische test van de projectreportage.','html':'<h2>Details</h2><p>Reportage-inhoud.</p><script>alert(1)</script>','menu':'geen','online':'1','indexeren':'0'})
            check(code == 302, 'Extra projectpagina aangemaakt')
            pages = json.loads((root/'public_html/data/paginas-eigen.json').read_text())
            check('project-controle' in pages, 'Extra pagina opgeslagen in JSON')
            # De admin kan een concept bekijken; maak de status via dezelfde pagina-API expliciet online.
            status_data = json.loads((root/'public_html/data/paginastatus.json').read_text())
            if not status_data.get('eigen:project-controle',{}).get('actief',True):
                admin.request('/beheer/paginas.php', {'csrf':token,'actie':'online','pagina':'eigen:project-controle'})
            body = anonymous.request('/project-controle')[1]
            check('Reportage-inhoud.' in body and '<script>alert' not in body, 'Extra pagina en HTML-filter werken')
            check('/project-controle?thema=galerie' in anonymous.request('/galerie')[1], 'Project linkt naar eigen fotoreportage')

            row_revision = hashlib.sha256(b'[]').hexdigest()
            code, body, _ = admin.request('/beheer/rij-opslaan-ajax.php', {'csrf':token,'zone':'eigen-project-controle','actie':'opslaan','revisie':row_revision,'id':'','indeling':'tekst','achtergrond':'paper','titel':'Fotoreportage aanvulling','html':'<p>Een flexibel inhoudsblok.</p>','na':'','knop_tekst':'','knop_url':''})
            check(code == 200 and json.loads(body).get('ok'), 'Flexibel blok opslaan')
            check('Een flexibel inhoudsblok.' in anonymous.request('/project-controle')[1], 'Flexibel blok publiek gekoppeld')

            token = admin.token('/beheer/navigatie.php')
            admin.request('/beheer/navigatie.php', {'csrf':token,'actie':'opslaan','label':'Controle-link','url':'project-controle','locatie':'hoofd','actief':'1'})
            check(all('Controle-link' in anonymous.request('/'+t)[1] for t in ('atelier','blauwdruk','galerie','werkplaats')), 'CMS-navigatie werkt in alle ontwerpen')
            token = admin.token('/beheer/seo.php?pagina=atelier')
            admin.request('/beheer/seo.php?pagina=atelier', {'csrf':token,'pagina':'atelier','titel':'Technische SEO-titel','omschrijving':'Een testomschrijving.'})
            check('<title>Technische SEO-titel</title>' in anonymous.request('/atelier')[1], 'SEO gekoppeld aan de publieke head')
            admin.request('/beheer/paginas.php', {'csrf':token,'actie':'online','pagina':'atelier'})
            check(anonymous.request('/atelier')[0] == 404, 'Verborgen vaste pagina is niet publiek bereikbaar')
            admin.request('/beheer/paginas.php', {'csrf':token,'actie':'online','pagina':'atelier'})
            admin.request('/beheer/redirects.php', {'csrf':token,'actie':'opslaan','van':'oud-project','naar':'/galerie','code':'301','actief':'1'})
            code, _, headers = anonymous.request('/oud-project')
            check(code == 301 and headers.get('Location') == '/galerie', 'CMS-redirect werkt')

            token = anonymous.token('/atelier')
            submission = {'csrf':token,'formulier':'contact','naam':'Testpersoon','email':'controle@example.invalid','plaats':'Testplaats','bericht':'Een technische proefaanvraag.','website':''}
            code, body, _ = anonymous.request('/atelier', dict(submission, csrf='ongeldig'))
            check('Je sessie is verlopen' in body and json.loads((root/'public_html/data/inzendingen.json').read_text()) == [], 'Formulier weigert ongeldige CSRF')
            code, body, _ = anonymous.request('/atelier', dict(submission, email='ongeldig'))
            check('een geldig e-mailadres' in body, 'Formuliervalidatie controleert e-mail')
            code, body, headers = anonymous.request('/atelier', submission)
            stored = json.loads((root/'public_html/data/inzendingen.json').read_text())
            check(code == 303 and len(stored) == 1 and headers.get('Location') == '/atelier#contact', 'Formulieropslag en redirect na verzenden')
            body = anonymous.request('/atelier')[1]
            check('Je proefaanvraag is opgeslagen.' in body, 'Succesmelding alleen na opslag')
            check(len(json.loads((root/'public_html/data/inzendingen.json').read_text())) == 1, 'Vernieuwen maakt geen dubbele inzending')
            code, body, _ = anonymous.request('/atelier', dict(submission, website='spam'))
            check('Ongeldige inzending.' in body, 'Honeypot werkt')
            for _ in range(4):
                anonymous.request('/atelier', submission)
            check('Te veel inzendingen.' in anonymous.request('/atelier', submission)[1], 'Formulierrate-limit werkt')
            check(all('inzendingen' not in p.name for p in (root/'public_html/data/versies').rglob('*.json')), 'Persoonsgegevens staan niet in herstelversies')

            token = admin.token('/beheer/gebruikers.php')
            for role in ('redacteur','seo'):
                user_password = secrets.token_urlsafe(24)
                admin.request('/beheer/gebruikers.php', {'csrf':token,'actie':'toevoegen','sleutel':'controle-'+role,'naam':'Controle '+role,'rol':role,'wachtwoord':user_password,'bevestig':password})
                client = Client(base)
                role_token = client.token('/beheer/login.php')
                client.request('/beheer/login.php', {'csrf':role_token,'gebruiker':'controle-'+role,'wachtwoord':user_password})
                check(client.request('/beheer/gebruikers.php')[0] == 403, role + ' mag geen accounts beheren')
                if role == 'seo':
                    check(client.request('/beheer/kernteksten.php')[0] == 403 and client.request('/beheer/seo.php')[0] == 200, 'SEO-rol is correct begrensd')
                else:
                    check(client.request('/beheer/kernteksten.php')[0] == 200, 'Redacteur mag inhoud beheren')
            token = admin.token('/beheer/index.php')
            admin.request('/beheer/logout.php', {'csrf':token})
            check(admin.request('/beheer/kernteksten.php')[0] == 302, 'Uitloggen trekt toegang in')
            check(not any((root/'public_html/data/bezoekers').glob('*.json')), 'Bezoekerslogging blijft uit')
            log.flush()
            check(not re.search(r'(?:PHP (?:Warning|Fatal error|Deprecated)|Uncaught)', log_path.read_text()), 'Geen PHP-fouten tijdens integratietest')
        finally:
            server.terminate()
            server.wait(timeout=5)

print(f'{len(checks)} niet-visuele controles geslaagd. Tijdelijke accounts, uploads en inzendingen zijn verwijderd.')
