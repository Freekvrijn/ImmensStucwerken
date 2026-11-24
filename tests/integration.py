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

            for theme in ('', 'blauwdruk', 'privacy', 'offerte'):
                code, body, _ = anonymous.request('/' + theme)
                check(code == 200 and '<html lang="nl">' in body and 'noindex,nofollow' in body, 'Publieke route en noindex: /' + theme)
                check(not re.search(r'(?:Warning|Fatal error|Deprecated):', body), 'Geen PHP-melding: /' + theme)
                parser = Assets(); parser.feed(body)
                check(parser.h1s == 1, 'Eén hoofdkop: /' + theme)
                check(all(anchor in parser.ids for anchor in parser.anchors), 'Ankerbestemmingen aanwezig: /' + theme)
                for url in parser.urls:
                    check(url.startswith('/') and anonymous.request(url)[0] == 200, 'Lokaal asset beschikbaar: ' + url.split('?')[0])
            for removed in ('atelier', 'galerie', 'werkplaats'):
                for path in ('/'+removed, '/'+removed+'/', '/'+removed+'/privacy', '/?thema='+removed):
                    check(anonymous.request(path)[0] == 404, 'Vervallen ontwerp is verwijderd: '+path)
            homepage = anonymous.request('/')[1]
            check('thema-blauwdruk' in homepage and 'thema-menu' not in homepage and 'previewbalk' not in homepage, 'Alleen Blauwdruk, zonder themakeuze')
            check(homepage.count('data-project=') == 9 and homepage.count('data-project-dialog=') == 9, 'Alle negen foto’s hebben een fotodialoog')
            admin_pages = admin.request('/beheer/paginas.php')[1]
            check(not any(name in admin_pages for name in ('Atelier', 'Galerie', 'Werkplaats')), 'Vervallen ontwerpen zijn ook uit het CMS verwijderd')
            check(anonymous.request('/bestaat-niet')[0] == 404, 'Onbekende pagina geeft 404')
            check(anonymous.request('/?thema[]=atelier')[0] == 404, 'Ongeldige themainvoer geeft 404')
            for path in ('/data/content.json','/_lib/config.php','/data/geoip/ip2location-DB1LITEBINIPV6.bin','/.htaccess','/themas/blauwdruk.php'):
                check(anonymous.request(path)[0] == 403, 'Gevoelige route geblokkeerd: ' + path)
            check('ip2location.com' in anonymous.request('/privacy')[1], 'Verplichte lokale GeoIP-bronvermelding aanwezig')

            for path in ('paginas.php','kernteksten.php','collectie.php','navigatie.php','seo.php','media.php','gebruikers.php','versies.php','formulieren.php','toegang.php','geoip.php'):
                code, body, _ = admin.request('/beheer/' + path)
                check(code == 200 and not re.search(r'(?:Warning|Fatal error|Deprecated):', body), 'Beheerscherm technisch geldig: ' + path)
            token = admin.token('/beheer/kernteksten.php?pagina=home')
            admin.request('/beheer/kernteksten.php?pagina=home', {'csrf':token,'_pagina':'home','blauwdruk_titel':'Technische controle van de titel'})
            check('Technische controle van de titel' in anonymous.request('/')[1], 'Pagina-inhoud wordt publiek bijgewerkt')
            check(any((root/'public_html/data/versies').rglob('*.json')), 'Automatische herstelversie gemaakt')

            token = admin.token('/beheer/media.php')
            code, body, _ = admin.request('/beheer/media.php', {'csrf':token,'actie':'upload','alt':'Technische uploadcontrole','titel':'Controlefoto'}, {'bestand':('controle.jpg','image/jpeg',(PROJECT/'public_html/assets/img/detail.jpg').read_bytes())})
            check('Afbeelding geüpload.' in body and bool(list((root/'public_html/assets/img/uploads').glob('*.jpg'))), 'Beveiligde afbeeldingupload')
            uploaded = next((root/'public_html/assets/img/uploads').glob('*.jpg'))

            token = admin.token('/beheer/collectie.php?actie=nieuw')
            admin.request('/beheer/collectie.php', {'csrf':token,'actie':'opslaan','titel':'Controleproject','slug':'controle','categorie':'Stucwerk','datum':'2026-11-12','samenvatting':'Projectomschrijving voor de integratietest.','tekst':'<p>Extra projectinformatie.</p>','actief':'1'}, {'afbeelding_upload':('project.jpg','image/jpeg',uploaded.read_bytes())})
            for theme in ('','blauwdruk'):
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
            check('/project-controle' in anonymous.request('/')[1], 'Project linkt naar eigen fotoreportage')

            row_revision = hashlib.sha256(b'[]').hexdigest()
            code, body, _ = admin.request('/beheer/rij-opslaan-ajax.php', {'csrf':token,'zone':'eigen-project-controle','actie':'opslaan','revisie':row_revision,'id':'','indeling':'tekst','achtergrond':'paper','titel':'Fotoreportage aanvulling','html':'<p>Een flexibel inhoudsblok.</p>','na':'','knop_tekst':'','knop_url':''})
            check(code == 200 and json.loads(body).get('ok'), 'Flexibel blok opslaan')
            check('Een flexibel inhoudsblok.' in anonymous.request('/project-controle')[1], 'Flexibel blok publiek gekoppeld')

            token = admin.token('/beheer/navigatie.php')
            admin.request('/beheer/navigatie.php', {'csrf':token,'actie':'opslaan','label':'Controle-link','url':'project-controle','locatie':'hoofd','actief':'1'})
            check(all('Controle-link' in anonymous.request('/'+t)[1] for t in ('','blauwdruk')), 'CMS-navigatie werkt op de homepage en Blauwdruk-alias')
            token = admin.token('/beheer/seo.php?pagina=home')
            admin.request('/beheer/seo.php?pagina=home', {'csrf':token,'pagina':'home','titel':'Technische SEO-titel','omschrijving':'Een testomschrijving.'})
            check('<title>Technische SEO-titel</title>' in anonymous.request('/')[1], 'SEO gekoppeld aan de publieke head')
            admin.request('/beheer/paginas.php', {'csrf':token,'actie':'online','pagina':'privacy'})
            check(anonymous.request('/privacy')[0] == 404, 'Verborgen vaste pagina is niet publiek bereikbaar')
            admin.request('/beheer/paginas.php', {'csrf':token,'actie':'online','pagina':'privacy'})
            admin.request('/beheer/redirects.php', {'csrf':token,'actie':'opslaan','van':'oud-project','naar':'/blauwdruk','code':'301','actief':'1'})
            code, _, headers = anonymous.request('/oud-project')
            check(code == 301 and headers.get('Location') == '/blauwdruk', 'CMS-redirect werkt')

            token = anonymous.token('/')
            submission = {'csrf':token,'formulier':'contact','naam':'Testpersoon','email':'controle@example.invalid','plaats':'Testplaats','bericht':'Een technische proefaanvraag.','website':''}
            code, body, _ = anonymous.request('/', dict(submission, csrf='ongeldig'))
            check('Je sessie is verlopen' in body and json.loads((root/'public_html/data/inzendingen.json').read_text()) == [], 'Formulier weigert ongeldige CSRF')
            code, body, _ = anonymous.request('/', dict(submission, email='ongeldig'))
            check('een geldig e-mailadres' in body, 'Formuliervalidatie controleert e-mail')
            code, body, headers = anonymous.request('/', submission)
            stored = json.loads((root/'public_html/data/inzendingen.json').read_text())
            check(code == 303 and len(stored) == 1 and headers.get('Location') == '/#contact', 'Formulieropslag en redirect na verzenden')
            body = anonymous.request('/')[1]
            check('Je proefaanvraag is opgeslagen.' in body, 'Succesmelding alleen na opslag')
            check(len(json.loads((root/'public_html/data/inzendingen.json').read_text())) == 1, 'Vernieuwen maakt geen dubbele inzending')
            code, body, _ = anonymous.request('/', dict(submission, website='spam'))
            check('Ongeldige inzending.' in body, 'Honeypot werkt')
            for _ in range(4):
                anonymous.request('/', submission)
            check('Te veel inzendingen.' in anonymous.request('/', submission)[1], 'Formulierrate-limit werkt')
            check(all('inzendingen' not in p.name for p in (root/'public_html/data/versies').rglob('*.json')), 'Persoonsgegevens staan niet in herstelversies')

            # Offertes: prijs op de server, privé-PDF, snapshots en beveiligd prijsbeheer.
            check(anonymous.request('/beheer/offertes.php')[0] == 302, 'Offertebeheer vereist inloggen')
            def quote_form(client):
                code, body, _ = client.request('/offerte')
                check(code == 200, 'Offerteformulier is bereikbaar')
                hidden = {}
                for field in ('csrf', 'offerte_nonce', 'revisie'):
                    match = re.search(r'name="'+field+r'" value="([a-f0-9]+)"', body)
                    check(bool(match), 'Offerteveld beschikbaar: '+field)
                    hidden[field] = match[1]
                return dict(hidden, **{'aantallen[wanden]':'44','aantallen[plafonds]':'16','pand':'onbekend','naam':'Offerte Testpersoon','email':'offerte@example.invalid','adres':'Teststraat 1','postcode':'5271 KE','plaats':'Testplaats','telefoon':'','opmerking':'Technische offertecontrole.','website':''})
            quote = quote_form(anonymous)
            quote_dir = root/'public_html/data/offertes'
            for bad in ({'csrf':'ongeldig'}, {'aantallen[wanden]':'-1'}, {'aantallen[wanden]':'1e3'}, {'aantallen[wanden]':'0.001'}, {'aantallen[wanden]':'10000.01'}, {'aantallen[wanden][]':'5'}, {'aantallen[onbekend]':'10'}, {'aantallen[wanden]':'0','aantallen[plafonds]':'0'}, {'pand':'btw-verlegd'}, {'email':'ongeldig'}, {'postcode':'onbekend'}, {'website':'spam'}, {'offerte_nonce':'onbekend'}):
                code, body, _ = anonymous.request('/offerte',dict(quote,**bad))
                check(code == 422 and not list(quote_dir.glob('*.json')), 'Offerte weigert ongeldige invoer: '+str(list(bad)))
            quote = quote_form(anonymous)
            quote['totaal_cent'] = '1'  # Door de klant geposte prijzen worden nooit gebruikt.
            code, _, headers = anonymous.request('/offerte',quote)
            check(code == 303 and '/offerte?id=IMM-' in headers.get('Location',''), 'Offerte aangemaakt met eigen nummer')
            quote_location = headers['Location']
            quote_id = urllib.parse.parse_qs(urllib.parse.urlsplit(quote_location).query)['id'][0]
            saved = json.loads((quote_dir/(quote_id+'.json')).read_text())
            check(saved['berekening']['subtotaal_cent']==165000 and saved['berekening']['btw_cent']==34650 and saved['berekening']['totaal_cent']==199650, 'Offerte rekent 44+16 m², reiskosten en 21% btw exact, onafhankelijk van gepost totaal')
            check('Offerte Testpersoon' in anonymous.request(quote_location)[1], 'Aanvrager kan eigen offerte bekijken')
            outsider = Client(base)
            pdf_url = '/offerte-pdf.php?id='+quote_id
            check(outsider.request(quote_location)[0] == 404 and outsider.request(pdf_url)[0] == 404, 'Andere sessie kan offerte en PDF niet openen')
            check(anonymous.request('/offerte-pdf.php?id=../../auth')[0] == 404, 'PDF-endpoint weigert padmanipulatie')
            pdf_response = anonymous.opener.open(base+pdf_url)
            pdf_bytes = pdf_response.read()
            check(pdf_response.headers['Content-Type']=='application/pdf' and pdf_bytes.startswith(b'%PDF-1.4'), 'Aanvrager krijgt een echte PDF-download')
            pdf_path = root/'controle-offerte.pdf';pdf_path.write_bytes(pdf_bytes)
            if shutil.which('pdftotext'):
                extracted = subprocess.check_output(['pdftotext',str(pdf_path),'-'],text=True)
                check(all(value in extracted for value in (quote_id,'Offerte Testpersoon','1.996,50','346,50','150,00')), 'PDF bevat klant, nummer en correcte bedragen')
            code, _, headers = anonymous.request('/offerte',quote)
            check(code==303 and headers.get('Location')==quote_location and len(list(quote_dir.glob('*.json')))==1, 'Herhaald verzenden maakt geen dubbele offerte')
            stale = quote_form(anonymous)
            prices = json.loads((root/'public_html/data/offerte-instellingen.json').read_text())
            def settings_post(config, token, revision):
                post = {'csrf':token,'actie':'instellingen','revisie':revision,'reiskosten':'100','minimum':'0','btw_standaard':'21','woning_btw':'1','geldigheid_dagen':'30','voorwaarden':config['voorwaarden'],'actief':'1'}
                for service in config['diensten']:
                    prefix = 'diensten['+service['id']+']'
                    post.update({prefix+'[naam]':service['naam'],prefix+'[eenheid]':service['eenheid'],prefix+'[prijs]':'30' if service['id']=='wanden' else '40',prefix+'[actief]':'1'})
                for key,value in config['bedrijf'].items():post['bedrijf['+key+']']=value
                return post
            code, body, _ = admin.request('/beheer/offertes.php?tab=tarieven')
            price_token = re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
            revision = re.search(r'name="revisie" value="([a-f0-9]+)"',body)[1]
            new_prices = settings_post(prices,price_token,revision)
            code, _, _ = admin.request('/beheer/offertes.php?tab=tarieven',dict(new_prices,csrf='ongeldig'))
            check(code==422 and json.loads((root/'public_html/data/offerte-instellingen.json').read_text())==prices, 'Prijsbeheer weigert ongeldige CSRF')
            code, _, _ = admin.request('/beheer/offertes.php?tab=tarieven',dict(new_prices,reiskosten='-1'))
            check(code==422, 'Prijsbeheer weigert negatief tarief')
            code, _, _ = admin.request('/beheer/offertes.php?tab=tarieven',new_prices)
            check(code==303 and json.loads((root/'public_html/data/offerte-instellingen.json').read_text())['diensten'][0]['prijs_cent']==3000, 'Beheerder kan tarieven wijzigen')
            check(bool(list((root/'public_html/data/versies').glob('offerte-instellingen--*.json'))), 'Prijswijziging krijgt een herstelversie')
            check(admin.request('/beheer/offertes.php?tab=tarieven',new_prices)[0]==409, 'Prijsbeheer detecteert gelijktijdige wijziging')
            code, body, _ = anonymous.request('/offerte',stale)
            check(code==409 and len(list(quote_dir.glob('*.json')))==1, 'Verouderde prijsberekening vraagt controle van nieuwe tarieven')
            check(anonymous.opener.open(base+pdf_url).read()==pdf_bytes, 'Bestaande offerte en PDF blijven gelijk na prijswijziging')
            current = quote_form(anonymous)
            code, _, headers = anonymous.request('/offerte',dict(current,pand='woning-oud'))
            new_id = urllib.parse.parse_qs(urllib.parse.urlsplit(headers['Location']).query)['id'][0]
            new_saved = json.loads((quote_dir/(new_id+'.json')).read_text())
            check(code==303 and new_saved['berekening']['subtotaal_cent']==206000 and new_saved['berekening']['btw_percentage']==9 and new_saved['berekening']['totaal_cent']==224540, 'Nieuwe offerte gebruikt nieuwe tarieven en 9% voor opgegeven oudere woning')
            check(admin.request(pdf_url)[0]==200 and quote_id in admin.request('/beheer/offertes.php')[1], 'Beheerder kan ontvangen offerte en PDF openen')
            code, _, _ = admin.request('/beheer/offertes.php',{'csrf':price_token,'actie':'status','id':quote_id,'status':'in-behandeling'})
            check(code==303 and json.loads((quote_dir/(quote_id+'.json')).read_text())['status']=='in-behandeling', 'Beheerder kan offertestatus wijzigen')
            check(admin.request('/beheer/offertes.php',{'csrf':'ongeldig','actie':'verwijderen','id':quote_id})[0]==422 and (quote_dir/(quote_id+'.json')).exists(), 'Verwijderen vereist geldige CSRF')
            code, _, _ = admin.request('/beheer/offertes.php',{'csrf':price_token,'actie':'verwijderen','id':quote_id})
            check(code==303 and not (quote_dir/(quote_id+'.json')).exists() and anonymous.request(pdf_url)[0]==404, 'Offerte kan definitief verwijderd worden')
            for i in range(3):
                item=quote_form(anonymous)
                check(anonymous.request('/offerte',item)[0]==303, 'Offerte binnen snelheidslimiet')
            item=quote_form(anonymous)
            check(anonymous.request('/offerte',item)[0]==422, 'Offertes begrensd tot vijf per uur per IP-hash')
            expired=json.loads((quote_dir/(new_id+'.json')).read_text());expired['gemaakt_op']='2020-01-01T00:00:00+00:00';(quote_dir/(new_id+'.json')).write_text(json.dumps(expired))
            check(anonymous.request('/offerte-pdf.php?id='+new_id)[0]==404, 'Verlopen bewaartermijn sluit PDF-toegang af')
            admin.request('/beheer/offertes.php')
            check(not (quote_dir/(new_id+'.json')).exists(), 'Oude offertes worden opgeschoond')
            check(not any('offerte-' in p.name and 'offerte-instellingen' not in p.name for p in (root/'public_html/data/versies').glob('*.json')), 'Offertes met persoonsgegevens komen niet in herstelversies')
            check(anonymous.request('/data/offerte-instellingen.json')[0]==403 and anonymous.request('/data/offertes/'+new_id+'.json')[0]==403, 'Offertedata blijft afgeschermd via HTTP')

            private_id = next(path.stem for path in quote_dir.glob('*.json'))
            token = admin.token('/beheer/gebruikers.php')
            for role in ('redacteur','seo'):
                user_password = secrets.token_urlsafe(24)
                admin.request('/beheer/gebruikers.php', {'csrf':token,'actie':'toevoegen','sleutel':'controle-'+role,'naam':'Controle '+role,'rol':role,'wachtwoord':user_password,'bevestig':password})
                client = Client(base)
                role_token = client.token('/beheer/login.php')
                client.request('/beheer/login.php', {'csrf':role_token,'gebruiker':'controle-'+role,'wachtwoord':user_password})
                check(client.request('/beheer/gebruikers.php')[0] == 403, role + ' mag geen accounts beheren')
                check(client.request('/beheer/offertes.php')[0] == 403 and client.request('/offerte-pdf.php?id='+private_id)[0] == 404, role + ' mag geen tarieven of klantoffertes beheren')
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
