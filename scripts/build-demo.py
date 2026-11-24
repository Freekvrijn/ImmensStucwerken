#!/usr/bin/env python3
"""Exporteer alleen publieke HTML/assets naar docs voor GitHub Pages."""
import hashlib
import html
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import shutil
import subprocess
import sys
from urllib.parse import urlsplit, urlunsplit

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'public_html'
OUTPUT = ROOT / 'docs'
BASE = '/ImmensStucwerken/'
generated = []


def local_url(value):
    value = html.unescape(value)
    if not value.startswith('/') or value.startswith('//'):
        return value
    url = urlsplit(value)
    path = url.path.strip('/')
    query = url.query
    if path == 'privacy':
        query = ''
    source = SOURCE / path
    if source.is_file():
        if query.startswith('v='):
            query = 'v=' + hashlib.sha256(source.read_bytes()).hexdigest()[:12]
    elif path:
        path += '/'
    return urlunsplit(('', '', BASE + path, query, url.fragment))


def transform(source, privacy=False):
    source = re.sub(r'\sdata-inl-csrf="[^"]*"', '', source)
    source = re.sub(r'\sdata-veld(?:-afbeelding|-link)?="[^"]*"', '', source)
    source = re.sub(r'<input\b[^>]*name="csrf"[^>]*>', '', source)
    if privacy:
        text = '<div class="eigen-inhoud"><p>Deze online demo toont Immens Stucwerken in het gekozen ontwerp Blauwdruk. Het contactformulier en het beheer zijn niet actief. Er worden geen formuliergegevens opgeslagen en deze demo gebruikt geen CMS-sessiecookie of bezoekersstatistieken.</p><p>Afbeeldingen en lettertypen worden vanuit dezelfde website geladen. De site wordt aangeboden via GitHub Pages. GitHub kan technische toegangsgegevens verwerken volgens de <a href="https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement">privacyverklaring van GitHub</a>.</p></div>'
        source = re.sub(r'<div class="eigen-inhoud">.*?</div>', lambda _: text, source, flags=re.S)
    def demo_form(match):
        form = match[0]
        form = re.sub(r'\smethod="[^"]*"|\saction="[^"]*"', '', form)
        form = form.replace('class="contactformulier"', 'class="contactformulier" data-demo-form')
        form = re.sub(r'<(input|textarea|button)\b', r'<\1 disabled', form)
        return form.replace('Verstuur proefaanvraag', 'Formulier niet actief in demo')
    source = re.sub(r'<form class="contactformulier".*?</form>', demo_form, source, flags=re.S)
    def demo_quote(match):
        form = re.sub(r'\smethod="[^"]*"|\saction="[^"]*"', '', match[0])
        form = form.replace('<form ', '<form data-demo-form ', 1)
        form = re.sub(r'<input\b[^>]*name="(?:offerte_nonce|revisie)"[^>]*>', '', form)
        form = re.sub(r'<(?:input|textarea)\b(?=[^>]*name="(?:naam|email|telefoon|adres|postcode|plaats|opmerking)")', lambda m: m[0]+' disabled', form)
        form = form.replace('<button class="knop" type="submit">Maak mijn offerte en PDF</button>', '<button class="knop" type="submit" disabled>PDF aanmaken niet actief in demo</button>')
        return form
    source = re.sub(r'<form\b(?=[^>]*class="offerte-formulier").*?</form>', demo_quote, source, flags=re.S)
    source = source.replace('Je offerte verschijnt direct op het scherm. Er wordt geen e-mail verstuurd. Bewaar de PDF zelf.', 'In deze demo kun je een prijs berekenen. Het opslaan van gegevens en downloaden van een offerte werkt in de volledige PHP-website.')
    source = source.replace('Dit is een ontwerpvoorbeeld. Verstuur nog geen echte aanvraag.', 'Dit is een online ontwerpvoorbeeld. Het formulier is niet actief.')
    source = re.sub(r'\b(href|src)="([^"]*)"', lambda m: m[1] + '="' + html.escape(local_url(m[2]), quote=True) + '"', source)
    if re.search(r'data-inl-csrf|name="csrf"|<\?php', source):
        raise RuntimeError('Onverwachte serverinhoud in de statische export.')
    return source


def render(route='', theme=''):
    result = subprocess.run(['php', str(ROOT/'scripts/render-demo.php'), route, theme], cwd=ROOT, capture_output=True, text=True, check=True)
    if result.stderr or re.search(r'(?:Warning|Fatal error|Deprecated):', result.stdout):
        raise RuntimeError(result.stderr or result.stdout[:500])
    return transform(result.stdout, route == 'privacy')


def write(path, contents):
    target = OUTPUT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(re.sub(r'(?m)^[ \t]+$', '', contents))
    generated.append(path)


OUTPUT.mkdir(exist_ok=True)
# Verwijder exports van de vervallen ontwerpvarianten en oude privacykopieën.
for obsolete in ('atelier', 'galerie', 'werkplaats', 'blauwdruk/privacy'):
    if (OUTPUT/obsolete).is_dir():
        shutil.rmtree(OUTPUT/obsolete)
# Assets: alleen publieke CSS/JS, lettertypen en beelden. Nooit PHP, JSON-data of beheer.
for source in sorted((SOURCE/'assets').rglob('*')):
    if not source.is_file() or source.name.startswith('.') or 'uploads' in source.relative_to(SOURCE).parts:
        continue
    if source.suffix.lower() not in {'.css','.js','.svg','.jpg','.jpeg','.webp','.png','.gif','.ttf','.woff','.woff2','.txt'}:
        continue
    relative = source.relative_to(SOURCE).as_posix()
    target = OUTPUT / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, target)
    generated.append(relative)
write('index.html', render())
write('blauwdruk/index.html', render('blauwdruk'))
write('privacy/index.html', render('privacy', 'blauwdruk'))
write('offerte/index.html', render('offerte'))
write('beheer/index.html', render('beheer'))
write('404.html', render('404'))
write('robots.txt', 'User-agent: *\nDisallow: /\n')
write('.nojekyll', '')


class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
        self.ids = set()
        self.forms = []
        self.headings = 0
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        for key in ('src', 'href'):
            if key in attrs:
                self.links.append(attrs[key])
        if 'id' in attrs:
            self.ids.add(attrs['id'])
        if tag == 'form':
            self.forms.append(attrs)
        if tag == 'h1':
            self.headings += 1


count = 0
for page in OUTPUT.rglob('*.html'):
    parser = Links(); parser.feed(page.read_text())
    if parser.headings != 1:
        raise RuntimeError('Onjuiste hoofdkopstructuur: '+str(page))
    for form in parser.forms:
        if 'data-demo-form' not in form or 'action' in form or 'method' in form:
            raise RuntimeError('Actief formulier in export: '+str(page))
    for link in parser.links:
        url = urlsplit(link)
        if url.scheme or url.netloc:
            continue
        if not url.path:
            if url.fragment and html.unescape(url.fragment) not in parser.ids:
                raise RuntimeError('Ontbrekend anker: '+link)
            continue
        if not url.path.startswith(BASE):
            raise RuntimeError('Link buiten repositorypad: '+link)
        dest = OUTPUT / url.path[len(BASE):]
        if dest.is_dir():
            dest = dest / 'index.html'
        if not dest.is_file():
            raise RuntimeError('Ontbrekende bestemming: '+link)
        if url.fragment and dest.suffix == '.html':
            target_parser = Links(); target_parser.feed(dest.read_text())
            if url.fragment not in target_parser.ids:
                raise RuntimeError('Ontbrekend bestemmingsanker: '+link)
        count += 1
    if re.search(r'PHPSESSID|data-inl-csrf|name="csrf"', page.read_text()):
        raise RuntimeError('Sessiegegevens in statische pagina: '+str(page))
if any(p.suffix in {'.php','.json'} for p in OUTPUT.rglob('*')):
    raise RuntimeError('Backendbestanden in demo-export.')
print(f'Demo gebouwd: {len(list(OUTPUT.rglob("*.html")))} HTML-pagina’s, {count} interne links/assets gecontroleerd. Formulieren uitgeschakeld; geen CMS-data gepubliceerd.')
