import json
import os
import time
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright

CACHE_DIR = "data"
os.makedirs(CACHE_DIR, exist_ok=True)
CACHE_FILE = os.path.join(CACHE_DIR, "besoccer_standings_cache.json")

def safe_int(val):
    import re
    if not isinstance(val, str):
        val = str(val)
    val = val.replace('+','')
    digits = re.sub(r'[^\d\-]', '', val)
    try:
        return int(digits)
    except:
        return 0

def parse_standings(html):
    soup = BeautifulSoup(html, 'html.parser')
    standings = []
    
    rows = soup.find_all('tr', class_='row-body')
    for tr in rows[:20]:  # Only get the "Total" table, not Home/Away
        tds = tr.find_all('td')
        if len(tds) < 11:
            continue
            
        pos_el = tds[0].find('div')
        position = pos_el.get_text(strip=True) if pos_el else ""
        
        img_el = tds[1].find('img')
        logo = img_el.get('src', '') if img_el else ""
        
        name_el = tds[2].find('span', class_='team-name')
        name = name_el.get_text(strip=True) if name_el else ""
        
        form_elements = tds[2].find_all('span', class_='bg-match-res')
        form = [f.get_text(strip=True) for f in form_elements]
        
        points = tds[3].get_text(strip=True)
        played = tds[4].get_text(strip=True)
        won = tds[5].get_text(strip=True)
        drawn = tds[6].get_text(strip=True)
        lost = tds[7].get_text(strip=True)
        gf = tds[8].get_text(strip=True)
        ga = tds[9].get_text(strip=True)
        gd = tds[10].get_text(strip=True)
        
        team_data = {
            "rank": safe_int(position),
            "team": {
                "id": None, 
                "name": name,
                "logo": logo
            },
            "points": safe_int(points),
            "goalsDiff": safe_int(gd),
            "group": "La Liga",
            "form": "".join(["W" if f=="V" else "D" if f=="E" else "L" for f in form]),
            "status": "up",
            "description": "Promotion - Champions League (Group Stage)" if safe_int(position) <= 4 else None,
            "all": {
                "played": safe_int(played.split('-')[0]),
                "win": safe_int(won),
                "draw": safe_int(drawn),
                "lose": safe_int(lost),
                "goals": {
                    "for": safe_int(gf),
                    "against": safe_int(ga)
                }
            },
            "home": {},
            "away": {},
            "update": time.strftime('%Y-%m-%dT%H:%M:%S+00:00')
        }
        standings.append(team_data)
        
    return standings

def parse_players(html, type_stat):
    """
    Parse a BeSoccer rankings page. Returns list of player entries in API-Football format.
    type_stat: 'scorers' | 'assists' | 'yellow' | 'red' | 'minutes_per_goal' |
               'penalty_goals' | 'missed_penalties' | 'saves' | 'goalkeeper' | 'played'
    """
    soup = BeautifulSoup(html, 'html.parser')
    players = []
    
    for tr in soup.find_all('tr', class_='row-body'):
        tds = tr.find_all('td')
        if len(tds) < 3:
            continue

        # TD[0] = player photo, TD[1] = name+team link + team logo img
        player_img = tds[0].find('img') if len(tds) > 0 else None
        photo = player_img.get('src', '') if player_img else ""

        # Team logo is in TD[1] as an additional img element (after the name link)
        team_img = None
        if len(tds) > 1:
            imgs_in_td1 = tds[1].find_all('img')
            if imgs_in_td1:
                team_img = imgs_in_td1[0]  # first img in TD[1]
        team_logo = team_img.get('src', '') if team_img else ""

        # Player name and team name are bundled in the same <a> tag
        name_elem = tds[1].find('a') if len(tds) > 1 else None
        if name_elem:
            full_text = name_elem.get_text(separator='|', strip=True)
            parts = full_text.split('|')
            player_name = parts[0] if parts else ""
            team_name = parts[1] if len(parts) > 1 else ""
        else:
            player_name = ""
            team_name = ""

        stat_val = safe_int(tds[2].get_text(strip=True)) if len(tds) > 2 else 0

        # Build player_data structure mimicking API-Football format
        player_data = {
            "player": {
                "id": None,   # No player IDs available from BeSoccer
                "name": player_name,
                "photo": photo
            },
            "statistics": [
                {
                    "team": {
                        "id": None,
                        "name": team_name,
                        "logo": team_logo
                    },
                    "goals": {
                        "total": stat_val if type_stat == 'scorers' else None,
                        "assists": stat_val if type_stat == 'assists' else None,
                        "conceded": stat_val if type_stat == 'goalkeeper' else None,
                        "saves": stat_val if type_stat == 'saves' else None,
                    },
                    "cards": {
                        "yellow": stat_val if type_stat == 'yellow' else None,
                        "red": stat_val if type_stat == 'red' else None,
                    },
                    "games": {
                        "appearences": stat_val if type_stat == 'played' else None,
                        "minutes": stat_val if type_stat == 'minutes_per_goal' else None,
                    },
                    "penalty": {
                        "scored": stat_val if type_stat == 'penalty_goals' else None,
                        "missed": stat_val if type_stat == 'missed_penalties' else None,
                    },
                    "extra_stat": stat_val  # Raw stat value for easy access
                }
            ]
        }
        players.append(player_data)
        
        if len(players) >= 20:
            break
            
    return players

def scrape_url(url, wait_sec=2):
    import urllib.request
    print(f"Loading {url}")
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'es-ES,es;q=0.9,en;q=0.8'
    }
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=15) as response:
            html = response.read().decode('utf-8')
            import time
            time.sleep(wait_sec)
            return html
    except Exception as e:
        print(f"  UrlLib warning: {str(e)[:60]}")
    return ""

def run_scraper():
    print("Starting Urllib scraper for BeSoccer rankings...")
    
    # Leagues mapping: API_ID -> BeSoccer Slug
    supported_leagues = {
        140: 'primera',         # La Liga
        39:  'premier_league',  # Premier League
        135: 'serie_a',         # Serie A
        78:  'bundesliga',      # Bundesliga
        61:  'ligue_1'          # Ligue 1
    }

    base = "https://es.besoccer.com/competicion"
    
    for league_id, slug in supported_leagues.items():
        print(f"\n=====================================")
        print(f"Scraping League: {slug} (ID: {league_id})")
        print(f"=====================================")
        
        results = {}
        rankings_base = f"{base}/rankings/{slug}/2026"
        clasificacion_base = f"{base}/clasificacion/{slug}/2026"
        
        ranking_urls = {
            "standings":        (f"{clasificacion_base}", "standings"),
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
            try:
                html = scrape_url(url)
                
                if key == "standings":
                    data = parse_standings(html)
                    print(f"  -> {slug}: {len(data)} teams from standings.")
                    results[key] = [data]
                else:
                    data = parse_players(html, stat_type)
                    print(f"  -> {slug}: {len(data)} entries for {key}.")
                    results[key] = data

                if key == "topScorers":
                    with open(f'stats_debug_{league_id}.html', 'w', encoding='utf-8') as f:
                        f.write(html)

            except Exception as e:
                print(f"  !! Error scraping {key} ({url}): {e}")
                results[key] = [] if key != "standings" else [[]]

        cache_name = f'data/besoccer_standings_cache_{league_id}.json'
        output_file = os.path.join(os.path.dirname(__file__), cache_name)

        if results.get('standings') and len(results['standings'][0]) > 0:
            output_data = {
                "response": {
                    "league": {
                        "id": league_id, "name": slug, "season": 2025,
                        "standings": results['standings']
                    }
                },
                "standings": results['standings'],
                "topScorers":       {"response": results.get('topScorers', [])},
                "topAssists":       {"response": results.get('topAssists', [])},
                "topYellow":        {"response": results.get('topYellow', [])},
                "topRed":           {"response": results.get('topRed', [])},
                "topMinutesGoal":   {"response": results.get('topMinutesGoal', [])},
                "topPenaltyGoals":  {"response": results.get('topPenaltyGoals', [])},
                "topMissedPenalty": {"response": results.get('topMissedPenalty', [])},
                "topGoalkeeper":    {"response": results.get('topGoalkeeper', [])},
                "topSavedPenalty":  {"response": results.get('topSavedPenalty', [])},
                "topPlayed":        {"response": results.get('topPlayed', [])},
            }
            
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(output_data, f, ensure_ascii=False, indent=2)
                
            print(f"  [SUCCESS] Cache saved to {cache_name}")
        else:
            print(f"  [WARNING] No standings data found for {slug}. Cache not populated.")
            
    print("\nAll rankings data completed!")

if __name__ == '__main__':
    run_scraper()
