"""Recette isolée : aucun accès à OVH, aucun courriel ni paiement."""
import argparse, concurrent.futures, http.cookiejar, json, os, re, secrets
import socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

checks = []
def check(condition, name):
    if not condition: raise AssertionError(name)
    checks.append(name)

with tempfile.TemporaryDirectory(prefix='tempogym-recette-') as temporary:
    temporary = Path(temporary)
    private = temporary / 'private'
    sessions = temporary / 'sessions'
    sessions.mkdir()
    with socket.socket() as free:
        free.bind(('127.0.0.1', 0))
        port = free.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = {**os.environ, 'TG_PRIVATE_DIR': str(private), 'PHP_CLI_SERVER_WORKERS': '4'}
    log = open(temporary / 'php.log', 'w+')
    process = subprocess.Popen([args.php, '-n', '-d', f'session.save_path={sessions}', '-S', f'127.0.0.1:{port}', '-t', str(root), str(root / 'tests/router.php')], cwd=root, env=env, stdout=log, stderr=log, start_new_session=True)
    class Client:
        def __init__(self):
            self.cookies = http.cookiejar.CookieJar()
            self.open = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect()).open
        def request(self, path, method='GET', data=None, form=None, header=True):
            headers = {}
            raw = None
            if form is not None:
                raw = urllib.parse.urlencode(form).encode(); headers['Content-Type'] = 'application/x-www-form-urlencoded'
            elif data is not None:
                raw = json.dumps(data).encode(); headers['Content-Type'] = 'application/json'
            if header and method in ('POST', 'DELETE'):
                headers.update({'X-TG-Request': 'v4', 'Origin': base})
            request = urllib.request.Request(base + path, data=raw, headers=headers, method=method)
            try: response = self.open(request, timeout=15)
            except urllib.error.HTTPError as error: response = error
            body = response.read().decode('utf-8-sig')
            value = json.loads(body) if 'application/json' in response.headers.get('Content-Type', '') else body
            return response.code, value, response.headers
    try:
        for _ in range(60):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2): break
            except OSError: time.sleep(.05)
        visitor, admin, coach = Client(), Client(), Client()
        status, _, headers = visitor.request('/')
        check(status == 302 and headers.get('Location') == '/installation.php', 'Site vierge dirigé vers installation')
        status, page, _ = visitor.request('/installation.php')
        check(status == 200, 'Installation accessible sans exposer sa clé')
        token = re.search(r'name="csrf" value="([^"]+)"', page).group(1)
        key = (private / 'installation_key.txt').read_text().strip()
        admin_password, coach_password, visitor_password = [secrets.token_urlsafe(24) for _ in range(3)]
        status, _, _ = visitor.request('/installation.php', 'POST', form={'csrf': token, 'key': key, 'admin': admin_password, 'admin2': admin_password, 'coach': coach_password, 'coach2': coach_password, 'visitor': visitor_password, 'visitor2': visitor_password})
        check(status == 303 and (private / 'config.json').exists() and not (private / 'installation_key.txt').exists(), 'Installation réelle et suppression de la clé')
        _, gate, _ = visitor.request('/')
        check('Site en cours' in gate and 'TG_SERVER_DATA' not in gate, 'Écran de développement géré côté serveur')
        check(visitor.request('/api/storefront')[0] == 403, 'Catalogue protégé tant que le développement est fermé')
        csrf = re.search(r'name="csrf" value="([^"]+)"', gate).group(1)
        check(visitor.request('/', 'POST', form={'csrf': csrf, 'password': visitor_password})[0] == 303, 'Accès visiteur avec le mot de passe choisi')
        _, html, _ = visitor.request('/')
        check('window.TG_PASSWORD_MODE=true' in html and 'window.TG_DEMO_MODE = false' in html, 'Page chargée en mode serveur réel')
        check(admin.request('/api/orders')[0] == 403, 'Commandes privées avant connexion')
        check(admin.request('/api/login', 'POST', {'role': 'admin', 'password': 'incorrect'})[0] == 401, 'Mauvais mot de passe refusé')
        status, _, _ = admin.request('/api/login', 'POST', {'role': 'admin', 'password': admin_password})
        check(status == 200 and any(c.name == 'tg_session' and c.has_nonstandard_attr('HttpOnly') for c in admin.cookies), 'Connexion administrateur et cookie HttpOnly')
        check(admin.request('/api/development-settings', 'POST', {'enabled': False})[0] == 200, 'Ouverture publique persistée sur le serveur')
        public = Client()
        status, catalogue, _ = public.request('/api/storefront')
        check(status == 200 and len(catalogue['products']) >= 4, 'Catalogue partagé dans un autre navigateur')
        product = catalogue['products'][0]
        sku, size = product['sku'], product['sizes'][0]
        check(admin.request('/api/stock', 'POST', {'sku': sku, 'size': size, 'quantity': 1})[0] == 200, 'Stock initial enregistré par taille')
        order = {'sku': sku, 'size': size, 'first': '=TEST', 'last': 'Recette', 'group': 'Parkour', 'email': 'test@example.invalid', 'privacyAccepted': True}
        check(public.request('/api/orders', 'POST', order, header=False)[0] == 403, 'Requête de commande sans en-tête refusée')
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
            results = list(executor.map(lambda _: Client().request('/api/orders', 'POST', order), range(2)))
        check(sorted(r[0] for r in results) == [201, 409], 'Deux commandes concurrentes pour une seule unité : une acceptée, une refusée')
        _, orders, _ = admin.request('/api/orders')
        check(len(orders) == 1 and orders[0]['first'] == '=TEST', 'Commande visible dans une session administrateur distincte')
        identifier = orders[0]['id']
        stock = lambda: admin.request('/api/stock')[1]['stocks'][sku][size]
        check(stock() == 0, 'Réservation du stock sans quantité négative')
        _, csv, _ = admin.request('/api/orders.csv')
        check("'=TEST" in csv, 'Export CSV neutralise les débuts de formule')
        check(coach.request('/api/login', 'POST', {'role': 'coach', 'password': coach_password})[0] == 200, 'Connexion entraîneur séparée')
        check(all(coach.request(route)[0] == 403 for route in ['/api/orders', '/api/site', '/api/stock']), 'Entraîneur sans accès aux commandes, au contenu admin et aux stocks')
        resource = {'section': 'annonce', 'title': 'TEST de recette', 'description': 'Donnée fictive et locale', 'audience': 'Équipe', 'url': ''}
        check(coach.request('/api/coaches', 'POST', resource)[0] == 403, 'Publication de ressources réservée à l’administration')
        check(admin.request('/api/coaches', 'POST', resource)[0] == 201 and len(coach.request('/api/coaches')[1]) == 1, 'Ressource partagée entre administration et entraîneur')
        change = lambda state: admin.request('/api/order-status', 'POST', {'id': identifier, 'status': state})[0]
        check(change('Annulée') == 200 and stock() == 1, 'Annulation restitue le stock')
        check(change('Annulée') == 200 and stock() == 1, 'Annulation répétée ne double pas le stock')
        check(change('Nouvelle') == 200 and stock() == 0, 'Réactivation réserve de nouveau le stock')
        check(admin.request('/api/order-anonymize', 'POST', {'id': identifier})[0] == 409, 'Anonymisation refusée avant fin de traitement')
        check(change('Remise') == 200 and admin.request('/api/order-anonymize', 'POST', {'id': identifier})[0] == 200, 'Anonymisation après remise')
        _, orders, _ = admin.request('/api/orders')
        check(all(orders[0][key] == '' for key in ['first', 'last', 'group', 'email']), 'Données personnelles effacées dans la commande terminée')
        check(admin.request('/api/site', 'POST', {'PAGE_ACCUEIL': {'sous_titre': 'TEST DE SYNCHRONISATION'}})[0] == 200 and 'TEST DE SYNCHRONISATION' in public.request('/')[1], 'Modification du site partagée entre deux sessions')
        check(public.request('/api/orders')[0] == 403, 'Visiteur public sans accès à la liste des commandes')
        check(admin.request('/api/logout', 'POST', {})[0] == 200 and admin.request('/api/orders')[0] == 403, 'Déconnexion retire immédiatement les droits')
        print(f'{len(checks)} contrôles PHP réussis : installation, sessions, rôles, commandes simultanées, stock, ressources, CSV et anonymisation.')
    except Exception:
        raise
    finally:
        import signal
        os.killpg(process.pid, signal.SIGTERM)
        process.wait(timeout=10)
        log.close()
