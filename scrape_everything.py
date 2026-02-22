import os, json, time, re, urllib.request, subprocess
from urllib.error import HTTPError
import subprocess

API_KEY = "49e3e38af4a5cee7715902854368de85"
VPN_DIR = os.path.join(os.path.dirname(__file__), "VPN")
vpn_files = [f for f in os.listdir(VPN_DIR) if f.endswith('.conf')]
current_vpn_idx = -1

# Import functions from existing scrapers
from scrape_besoccer import parse_standings, parse_players
from scrape_besoccer_players import extract_besoccer_id_from_photo, scrape_player_page, find_player_link_from_rankings, parse_player_season, parse_last_matches

def rotate_vpn():
    global current_vpn_idx
    if current_vpn_idx >= 0:
        old_vpn = vpn_files[current_vpn_idx].replace('.conf', '')
        print(f"[*] Disconnecting old VPN: {old_vpn}...")
        subprocess.run(["wireguard", "/uninstalltunnelservice", old_vpn], capture_output=True)
        time.sleep(3)
    
    current_vpn_idx = (current_vpn_idx + 1) % len(vpn_files)
    new_vpn_name = vpn_files[current_vpn_idx]
    vpn_path = os.path.join(VPN_DIR, new_vpn_name)
    print(f"[*] Connecting to new VPN: {new_vpn_name}...")
    subprocess.run(["wireguard", "/installtunnelservice", vpn_path], capture_output=True)
    print("[*] Waiting 10s for handshake...")
    time.sleep(10)

def robust_scrape_html(url, retries=3):
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
        'Accept-Language': 'es-ES,es;q=0.9,en;q=0.8',
    }
    for attempt in range(retries):
        req = urllib.request.Request(url, headers=headers)
        try:
            return urllib.request.urlopen(req, timeout=12).read().decode('utf-8')
        except HTTPError as e:
            if e.code in (403, 429, 503):
                print(f"[!] Blocked by BeSoccer ({e.code}) on {url}! Rotating VPN...")
                rotate_vpn()
            else:
                return None
        except Exception as e:
            print(f"[!] Network error: {e}. Retrying {attempt+1}/{retries}...")
            time.sleep(3)
    return None

def fetch_api_football_leagues():
    cache_path = "data/api_football_leagues.json"
    if os.path.exists(cache_path):
        data = json.load(open(cache_path, encoding='utf-8'))
        if data.get('results', 0) > 0:
            return data
            
    print("[*] Downloading API-Football full leagues list...")
    import http.client
    conn = http.client.HTTPSConnection("v3.football.api-sports.io")
    headers = { 'x-apisports-key': API_KEY }
    conn.request("GET", "/leagues", headers=headers)
    res = conn.getresponse()
    resp = res.read()
    data = json.loads(resp)
    with open(cache_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False)
    return data

def generate_slugs(league_name, country_name):
    # Hardcoded overrides for major leagues where BeSoccer uses non-obvious slugs
    KNOWN_SLUGS = {
        "Premier League": ["premier"],
        "La Liga": ["primera_iberdrola", "primera", "laliga", "primera_division"],
        "Ligue 1": ["ligue-1", "ligue_1"],
        "Serie A": ["calcio", "serie-a", "serie_a"],
        "Bundesliga": ["bundesliga", "1_liga"],
        "Primera Division": ["primera"],
        "Serie B": ["serie-b"],
        "Championship": ["championship"],
        "Champions League": ["champions"],
        "Europa League": ["europa"],
        "UEFA Nations League": ["nations_league"],
        "Copa del Rey": ["copa_del_rey"],
        "FA Cup": ["fa_cup"],
        "Eredivisie": ["eredivisie"],
        "Primeira Liga": ["primeira_liga"],
        "Super Lig": ["super_lig"],
        "Russian Premier League": ["premier_league_russia"],
    }
    
    pre_slugs = KNOWN_SLUGS.get(league_name, [])
    
    # e.g., "Premier League", "England" -> ["premier_league", "england_premier_league", "premier-league"]
    name_norm = re.sub(r'[^a-z0-9]', '_', league_name.lower().strip())
    name_norm = re.sub(r'_+', '_', name_norm)
    c_norm = re.sub(r'[^a-z0-9]', '_', country_name.lower().strip() if country_name else "")
    
    slugs = list(pre_slugs)
    if name_norm == "primera_division":
        slugs += ["primera", f"{c_norm}_primera_division"]
    elif name_norm == "championship":
        slugs += ["championship"]
    
    slugs.extend([
        name_norm,
        name_norm.replace('_', '-'),
        f"{c_norm}_{name_norm}",
        f"{c_norm}-{name_norm.replace('_', '-')}"
    ])
    # unique preserve order
    seen = set()
    return [x for x in slugs if not (x in seen or seen.add(x))]

def scrape_league_index(league_id, slug, season=2026):
    base = f"https://es.besoccer.com/competicion"
    clasificacion_base = f"{base}/clasificacion/{slug}/{season}"
    rankings_base = f"{base}/rankings/{slug}/{season}"
    
    print(f"  [>] Trying index {clasificacion_base}...")
    html = robust_scrape_html(clasificacion_base)
    if not html or "No hay datos para mostrar" in html or "class=\"row-body\"" not in html:
        return False
        
    print(f"  [+] Match found for {slug}!")
    results = {}
    
    soup = BeautifulSoup(html, 'html.parser')
    team_urls = []
    for tr in soup.find_all('tr', class_='row-body'):
        tds = tr.find_all('td')
        if len(tds) < 3: continue
        a_tag = tds[2].find('a')
        if a_tag and a_tag.get('href'):
            team_urls.append(a_tag['href'])
            
    results["standings"] = [parse_standings(html)]
    
    ranking_urls = {
        "topScorers":       (f"{rankings_base}/goleadores", "scorers"),
        "topAssists":       (f"{rankings_base}/asistencias", "assists"),
        "topYellow":        (f"{rankings_base}/tarjetas-amarillas", "yellow"),
        "topRed":           (f"{rankings_base}/tarjetas-rojas", "red"),
        "topMinutesGoal":   (f"{rankings_base}/minutos-por-gol", "minutes_per_goal"),
        "topPenaltyGoals":  (f"{rankings_base}/goles-penalti", "penalty_goals"),
        "topMissedPenalty": (f"{rankings_base}/penaltis-fallados", "missed_penalties"),
        "topGoalkeeper":    (f"{rankings_base}/mejor-portero", "goalkeeper"),
        "topSavedPenalty":  (f"{rankings_base}/penaltis-parados", "saves"),
        "topPlayed":        (f"{rankings_base}/more-minutes", "played"),
    }
    
    for key, (url, stat_type) in ranking_urls.items():
        r_html = robust_scrape_html(url)
        if r_html:
            results[key] = parse_players(r_html, stat_type)
        else:
            results[key] = []
            
    cache_name = f'data/besoccer_standings_cache_{league_id}.json'
    output_data = {
        "response": {"league": {"id": league_id, "name": slug, "season": 2025, "standings": results['standings']}},
        "standings": results['standings'],
        "teams": team_urls
    }
    for k in ranking_urls.keys():
        output_data[k] = {"response": results.get(k, [])}
        
    with open(cache_name, 'w', encoding='utf-8') as f:
        json.dump(output_data, f, ensure_ascii=False, indent=2)
        
    return True

def scrape_players_for_league(league_id):
    cache_name = f'data/besoccer_standings_cache_{league_id}.json'
    if not os.path.exists(cache_name): return
    
    standings = json.load(open(cache_name, encoding='utf-8'))
    players_to_scrape = {}
    
    for cat_key, cat_data in standings.items():
        if cat_key == 'standings': continue
        for entry in (cat_data.get('response') or []):
            name = entry['player']['name']
            if not name: continue
            if name not in players_to_scrape:
                players_to_scrape[name] = {
                    'photo': entry['player']['photo'],
                    'besoccer_url': f"https://es.besoccer.com/jugador/{name.lower().replace(' ','-')}-{extract_besoccer_id_from_photo(entry['player']['photo'])}"
                }
                
    team_urls = standings.get('teams', [])
    for url in team_urls:
        if '/equipo/' in url:
            plantilla_url = url.replace('/equipo/', '/equipo/plantilla/')
            print(f"  [>] Scraping squad: {plantilla_url}")
            p_html = robust_scrape_html(plantilla_url)
            if p_html:
                p_soup = BeautifulSoup(p_html, 'html.parser')
                # Players are usually links in the squad list, e.g. <a href="https://es.besoccer.com/jugador/..." class="name">
                # Or within table rows
                for a in p_soup.find_all('a'):
                    href = a.get('href', '')
                    if '/jugador/' in href and not href.endswith('/jugador/'):
                        name = href.split('/')[-1]
                        if name not in players_to_scrape:
                            players_to_scrape[name] = {
                                'photo': '',
                                'besoccer_url': href
                            }
                
    player_cache_file = "data/besoccer_player_cache.json"
    results = {}
    if os.path.exists(player_cache_file):
        results = json.load(open(player_cache_file, encoding='utf-8'))
        
    print(f"  [>] Scraping {len(players_to_scrape)} players for league {league_id}...")
    count = 0
    for name, info in players_to_scrape.items():
        if name in results and results[name].get('season_stats', {}).get('partidos', 0) > 0:
            continue
            
        url = info['besoccer_url']
        html = robust_scrape_html(url)
        if not html:
            continue
        try:
            results[name] = {
                'name': name, 'besoccer_url': url, 'photo': info['photo'],
                'season_stats': parse_player_season(html),
                'last_matches': parse_last_matches(html),
                'scraped_at': time.strftime('%Y-%m-%dT%H:%M:%S')
            }
            print(f"      [V] {name} scraped.")
            count += 1
            if count % 10 == 0:
                with open(player_cache_file, 'w', encoding='utf-8') as f:
                    json.dump(results, f, ensure_ascii=False)
        except Exception as e:
            print(f"      [X] Error parsing {name}: {e}")
            
    with open(player_cache_file, 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False)

def main():
    print("==================================================")
    print(" UNIVERSAL BESOCCER SCRAPER W/ VPN AUTO-ROTATION  ")
    print("==================================================")
    
    leagues_data = fetch_api_football_leagues()
    all_leagues = leagues_data.get("response", [])
    
    # Sort or filter leagues so we do the big ones first or just go in order.
    # We will just go in order.
    mapped_file = "data/besoccer_mapped_leagues.json"
    mapped = {}
    if os.path.exists(mapped_file):
        mapped = json.load(open(mapped_file))
        
    print(f"[*] Found {len(all_leagues)} leagues in API-Football. Currently mapped: {len(mapped)}")
    
    for l in all_leagues:
        l_id = str(l['league']['id'])
        if l_id in mapped:
            continue
            
        name = l['league']['name']
        country = l['country']['name']
        
        print(f"\n[*] Processing L{l_id}: {name} ({country})")
        slugs = generate_slugs(name, country)
        
        found = False
        for slug in slugs:
            if scrape_league_index(l_id, slug):
                mapped[l_id] = slug
                with open(mapped_file, 'w') as f:
                    json.dump(mapped, f)
                found = True
                
                # Now scrape players for this league
                scrape_players_for_league(l_id)
                break
                
        if not found:
            print(f"  [-] Could not map {name} to BeSoccer.")
            # Map as empty so we don't try again
            mapped[l_id] = None
            with open(mapped_file, 'w') as f:
                json.dump(mapped, f)
                
    # Finally, run the rate-limited API-Football player scraper
    try:
        import scrape_api_players
        scrape_api_players.scrape_api_players_routine()
    except Exception as e:
        print(f"[!] Error running scrape_api_players_routine: {e}")

if __name__ == "__main__":
    main()
