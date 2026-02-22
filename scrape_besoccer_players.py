"""
BeSoccer individual player stats scraper.
Scrapes season 2025/26 stats and last matches streak for each player.
Results stored in data/besoccer_player_cache.json keyed by BeSoccer player ID.

Strategy:
- We know the BeSoccer player ID from the photo URL in rankings cache:
  e.g. https://cdn.resfu.com/media/players/medium/234474.jpg -> besoccer_id = 234474
- We can construct the search URL: https://es.besoccer.com/jugador/name-BESOCCER_ID
  but we need the slug. Use BeSoccer search endpoint or use the stored link.
  ACTUALLY: each ranking page tr links to /jugador/slug - let's grab that link during ranking scraping.
  
This script does TWO things:
1. (RE)SCRAPE rankings to also capture player profile links
2. For each player, scrape their profile page and extract season stats

Run: python scrape_besoccer_players.py
"""
import time, json, os, re

from bs4 import BeautifulSoup

CACHE_FILE = os.path.join(os.path.dirname(__file__), 'data', 'besoccer_player_cache.json')
STANDINGS_CACHE_FILE = os.path.join(os.path.dirname(__file__), 'data', 'besoccer_standings_cache.json')

def safe_int(val):
    if val is None:
        return 0
    val = str(val).strip().replace("'", "").replace(",", "")
    digits = re.sub(r'[^\d\-]', '', val)
    try:
        return int(digits)
    except:
        return 0

def safe_float(val):
    if val is None:
        return 0.0
    val = str(val).strip()
    m = re.search(r'[\d]+\.?[\d]*', val)
    return float(m.group()) if m else 0.0

def scrape_player_page(url, wait_sec=2):
    import urllib.request
    """Scrape a BeSoccer player profile page."""
    print(f"  Scraping: {url}")
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=15) as response:
            html = response.read().decode('utf-8')
            time.sleep(wait_sec)
            return html
    except Exception as e:
        print(f"    urllib warning: {str(e)[:60]}")
    return ""

def parse_player_season(html):
    """Parse the #mod_player_season panel for 2025/26 stats."""
    soup = BeautifulSoup(html, 'html.parser')
    result = {
        'partidos': 0,
        'minutos': 0,
        'goles': 0,
        'asistencias': 0,
        'amarillas': 0,
        'rojas': 0,
    }
    
    # Find the season panel
    panel = soup.find(id='mod_player_season')
    if not panel:
        # Try to find any panel with "2025" in title
        for h2 in soup.find_all('h2', class_='panel-title'):
            if '2025' in h2.get_text():
                panel = h2.find_parent('div', class_='panel')
                break
    
    if not panel:
        print("    WARNING: mod_player_season not found")
        return result
    
    # Find all item-col divs inside the panel-body
    body = panel.find(class_='panel-body')
    if not body:
        return result
    
    cols = body.find_all('div', class_='item-col')
    for col in cols:
        other_line = col.find(class_='other-line')
        main_line = col.find(class_='main-line')
        if not other_line or not main_line:
            continue
        
        label_divs = [d.get_text(strip=True) for d in other_line.find_all('div')]
        label = ' '.join(label_divs).lower()
        
        main_text = main_line.get_text(separator=' ', strip=True)
        
        if 'partido' in label:
            result['partidos'] = safe_int(main_text)
        elif 'minuto' in label:
            result['minutos'] = safe_int(main_text.replace("'", ""))
        elif 'gol' in label and 'penalti' not in label:
            result['goles'] = safe_int(main_text)
        elif 'asistencia' in label:
            result['asistencias'] = safe_int(main_text)
        elif 'tarjeta' in label:
            # Format: "4/0" or span.yellow-card / span.red-card
            yellow_el = main_line.find('span', class_='yellow-card')
            red_el = main_line.find('span', class_='red-card')
            if yellow_el:
                result['amarillas'] = safe_int(yellow_el.get_text(strip=True))
            if red_el:
                result['rojas'] = safe_int(red_el.get_text(strip=True))
            else:
                # Try "X/Y" format
                parts = main_text.split('/')
                if len(parts) >= 2:
                    result['amarillas'] = safe_int(parts[0])
                    result['rojas'] = safe_int(parts[1])
    
    return result

def parse_last_matches(html):
    """Parse the #mod_last_matches_streak panel for recent matches."""
    soup = BeautifulSoup(html, 'html.parser')
    matches = []
    
    panel = soup.find(id='mod_last_matches_streak')
    if not panel:
        print("    WARNING: mod_last_matches_streak not found")
        return matches
    
    # Find spree-box links (actual matches)
    for a in panel.find_all('a', class_='spree-box', attrs={'data-cy': 'streakMatch'}):
        result_class = ''
        for cls in (a.get('class') or []):
            if cls in ('win', 'draw', 'lose'):
                result_class = cls
                break
        
        # Team abbreviations from spans
        spans = a.find_all('span')
        home_team = spans[0].get_text(strip=True) if len(spans) > 0 else ''
        away_team = spans[1].get_text(strip=True) if len(spans) > 1 else ''
        
        # Result score
        result_el = a.find(class_='result')
        score = result_el.get_text(strip=True) if result_el else ''
        
        # Date
        date_el = a.find(class_='date')
        date = date_el.get_text(strip=True) if date_el else ''
        
        # League logo
        league_img = a.find('img', class_='league-img')
        league_logo = league_img.get('src', '') if league_img else ''
        
        # Team shield images
        imgs = a.find_all('img', class_='shield')
        home_logo = imgs[0].get('src', '') if imgs else ''
        
        matches.append({
            'home': home_team,
            'away': away_team,
            'score': score,
            'date': date,
            'result': result_class,  # 'win', 'draw', 'lose'
            'league_logo': league_logo,
            'url': a.get('href', '')
        })
    
    return matches

def extract_besoccer_id_from_photo(photo_url):
    """Extract numeric BeSoccer player ID from photo URL."""
    # Pattern: /media/players/medium/234474.jpg or /media/players/234474.jpg
    m = re.search(r'/players/(?:medium/)?(\d+)\.jpg', photo_url or '')
    return m.group(1) if m else None

def find_player_link_from_rankings(html):
    """Parse ranking page HTML to get player profile links."""
    soup = BeautifulSoup(html, 'html.parser')
    links = {}
    for tr in soup.find_all('tr', class_='row-body'):
        tds = tr.find_all('td')
        if len(tds) < 2:
            continue
        # Player name link
        a = tds[1].find('a', href=True)
        if a and '/jugador/' in a.get('href', ''):
            name_text = a.get_text(separator='|', strip=True)
            player_name = name_text.split('|')[0]
            profile_url = a['href']
            if player_name:
                links[player_name] = profile_url
    return links

def run_player_scraper():
    """Main function to scrape BeSoccer player stats for all ranked players across top leagues."""
    
    supported_leagues = {
        140: 'primera',         # La Liga
        39:  'premier_league',  # Premier League
        135: 'serie_a',         # Serie A
        78:  'bundesliga',      # Bundesliga
        61:  'ligue_1'          # Ligue 1
    }
    
    players_to_scrape = {}  # name -> {photo, besoccer_id, besoccer_url}
    
    # 1. Collect unique players from all cached standings
    for league_id, slug in supported_leagues.items():
        standings_file = os.path.join(os.path.dirname(__file__), 'data', f'besoccer_standings_cache_{league_id}.json')
        if not os.path.exists(standings_file):
            print(f"Skipping {slug}, {standings_file} not found.")
            continue
            
        with open(standings_file, 'r', encoding='utf-8') as f:
            standings = json.load(f)
            
        for cat_key, cat_data in standings.items():
            if cat_key == 'standings':
                continue
            for entry in (cat_data.get('response') or []):
                name = entry['player']['name']
                photo = entry['player']['photo']
                if not name:
                    continue
                if name not in players_to_scrape:
                    bsc_id = extract_besoccer_id_from_photo(photo)
                    players_to_scrape[name] = {
                        'photo': photo,
                        'besoccer_id': bsc_id,
                        'besoccer_url': None,
                    }
                    
    print(f"\nFound {len(players_to_scrape)} globally unique players to scrape.")

    # 2. Collect profile links from ranking pages for all 5 leagues
    print("\nPhase 1: Collecting player profile links from rankings across all leagues...")
    profile_links = {}
    
    for league_id, slug in supported_leagues.items():
        print(f"  Fetching links for {slug}...")
        ranking_urls = [
            f"https://es.besoccer.com/competicion/rankings/{slug}/2026/goleadores",
            f"https://es.besoccer.com/competicion/rankings/{slug}/2026/asistencias",
            f"https://es.besoccer.com/competicion/rankings/{slug}/2026/tarjetas-amarillas"
        ]
        
        for url in ranking_urls:
            html = scrape_player_page(url, wait_sec=0.5)
            links = find_player_link_from_rankings(html)
            profile_links.update(links)

    print(f"  Total profile links collected globally: {len(profile_links)}")

    # Update players_to_scrape with found URLs
    for name, url in profile_links.items():
        if name in players_to_scrape:
            players_to_scrape[name]['besoccer_url'] = url

    remaining_without_url = {k: v for k, v in players_to_scrape.items() if not v['besoccer_url']}
    if remaining_without_url:
        print(f"  {len(remaining_without_url)} players without profile URL (will skip).")

    # Load existing player cache (to skip already-scraped)
    existing_cache = {}
    if os.path.exists(CACHE_FILE):
        with open(CACHE_FILE, 'r', encoding='utf-8') as f:
            existing_cache = json.load(f)
            
    results = dict(existing_cache)

    # STEP 3: Scrape individual player pages
    print(f"\nPhase 2: Scraping individual player stats pages (skipping cached)...")
    
    scraped_count = 0
    for name, info in players_to_scrape.items():
        url = info.get('besoccer_url')
        if not url:
            continue
        
        # Skip if already cached AND has valid stats (matches > 0)
        if name in results and results[name].get('season_stats', {}).get('partidos', 0) > 0:
            print(f"  SKIP (cached): {name}")
            continue
            
        try:
            html = scrape_player_page(url, wait_sec=1.5)
            season_stats = parse_player_season(html)
            last_matches = parse_last_matches(html)
            
            results[name] = {
                'name': name,
                'besoccer_url': url,
                'photo': info['photo'],
                'season_stats': season_stats,
                'last_matches': last_matches,
                'scraped_at': time.strftime('%Y-%m-%dT%H:%M:%S')
            }
            
            g = season_stats.get('goles', 0)
            p = season_stats.get('partidos', 0)
            print(f"  OK: {name} | {p} partidos, {g} goles")
            scraped_count += 1
            
            # Save progress every 5 players
            if scraped_count % 5 == 0:
                with open(CACHE_FILE, 'w', encoding='utf-8') as f:
                    json.dump(results, f, ensure_ascii=False, indent=2)
                print(f"  [Saved progress: {scraped_count} players]")
                
        except Exception as e:
            print(f"  ERROR scraping {name}: {e}")
            results[name] = {
                'name': name,
                'besoccer_url': url,
                'photo': info['photo'],
                'season_stats': {'partidos':0,'minutos':0,'goles':0,'asistencias':0,'amarillas':0,'rojas':0},
                'last_matches': [],
                'scraped_at': time.strftime('%Y-%m-%dT%H:%M:%S')
            }

    # Final save
    with open(CACHE_FILE, 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"\nDone! Scraped {scraped_count} new players. Total cache size: {len(results)}. Cache saved to {CACHE_FILE}")

if __name__ == '__main__':
    run_player_scraper()
