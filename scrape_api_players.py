import os, json, time, hashlib
import http.client
from urllib.error import HTTPError
from vpn_utils import rotate_vpn

API_KEY = "49e3e38af4a5cee7715902854368de85"
CACHE_DIR = "var/api_fallback_cache"
PROGRESS_FILE = "data/api_players_progress.json"
LEAGUES_FILE = "data/api_football_leagues.json"
MAX_REQUESTS_PER_RUN = 90  # Keep 10 requests for standard website activity

os.makedirs(CACHE_DIR, exist_ok=True)
os.makedirs("data", exist_ok=True)

def get_api_status():
    conn = http.client.HTTPSConnection("v3.football.api-sports.io")
    headers = {'x-apisports-key': API_KEY}
    conn.request("GET", "/status", headers=headers)
    res = conn.getresponse()
    if res.status != 200:
        return None
    data = json.loads(res.read())
    return data

def scrape_api_players_routine():
    print("==================================================")
    print(" API-FOOTBALL PLAYER RATE-LIMITED SCRAPER")
    print("==================================================")
    
    status = get_api_status()
    if not status:
        print("[!] Could not fetch API status.")
        return
        
    reqs = status.get('response', {}).get('requests', {})
    limit = reqs.get('limit_day', 100)
    current = reqs.get('current', 0)
    
    print(f"[*] API Status: {current}/{limit} requests used today.")
    
    remaining_allowed = MAX_REQUESTS_PER_RUN - current
    if remaining_allowed <= 0:
        print("[*] Daily fetch limit for scraper reached. Skipping until next reset.")
        return
        
    print(f"[*] Scraper will execute up to {remaining_allowed} queries this session.")
    
    if not os.path.exists(LEAGUES_FILE):
        print("[!] No leagues file found. Run main scraper first.")
        return
        
    leagues_data = json.load(open(LEAGUES_FILE, encoding='utf-8'))
    all_leagues = leagues_data.get("response", [])
    if not all_leagues:
        print("[!] Leagues list is empty.")
        return
        
    progress = {"league_idx": 0, "page": 1}
    if os.path.exists(PROGRESS_FILE):
        progress = json.load(open(PROGRESS_FILE, encoding='utf-8'))
        
    idx = progress.get("league_idx", 0)
    page = progress.get("page", 1)
    
    requests_made = 0
    conn = http.client.HTTPSConnection("v3.football.api-sports.io")
    headers = {'x-apisports-key': API_KEY}
    
    while idx < len(all_leagues) and requests_made < remaining_allowed:
        league_id = all_leagues[idx]['league']['id']
        league_name = all_leagues[idx]['league']['name']
        season = 2024 # API limits free plan current season? Some uses 2024, but 2025 is current.
        # Actually API-Football 2024 usually contains the main data, but 2025 is for new leagues.
        # FootballApiService uses 2024 in some places, 2025 in others. We'll use 2024 to ensure stats exist on free plan.
        # Wait, the frontend defaults to 2025 where possible... Let's use 2024 as it has full stats for free plan.
        
        endpoint = f"/players?league={league_id}&season=2024&page={page}"
        print(f"  [>] Fetching L{league_id} ({league_name}) - Page {page}...")
        
        try:
            conn.request("GET", endpoint, headers=headers)
            res = conn.getresponse()
            data = json.loads(res.read())
            requests_made += 1
            
            if "response" in data and data["response"]:
                players = data["response"]
                for p in players:
                    pid = p['player']['id']
                    # Clone PHP's exact json_encode
                    params_str = json.dumps({"id": pid, "season": 2024}, separators=(',', ':'))
                    cache_hash = hashlib.md5(params_str.encode('utf-8')).hexdigest()
                    cache_key = f"api_players_{cache_hash}"
                    cache_file = os.path.join(CACHE_DIR, f"{cache_key}.json")
                    
                    # Create the synthetic payload
                    payload = {
                        "get": "players",
                        "parameters": {"id": str(pid), "season": "2024"},
                        "errors": [],
                        "results": 1,
                        "paging": {"current": 1, "total": 1},
                        "response": [p]
                    }
                    
                    with open(cache_file, 'w', encoding='utf-8') as f:
                        json.dump(payload, f, ensure_ascii=False)
                        
                print(f"      [+] Cached {len(players)} players into Symfony Fallback System.")
                
            paging = data.get("paging", {})
            current_page = paging.get("current", 1)
            total_pages = paging.get("total", 1)
            
            if current_page >= total_pages:
                idx += 1
                page = 1
            else:
                page += 1
                
            # Save progress after every successful request
            with open(PROGRESS_FILE, 'w', encoding='utf-8') as f:
                json.dump({"league_idx": idx, "page": page}, f)
                
            time.sleep(1) # Prevent rapid API firing
            
        except Exception as e:
            print(f"[!] Error fetching API: {e} — rotating VPN and retrying...")
            rotate_vpn()
            # Recreate the connection after VPN change
            conn = http.client.HTTPSConnection("v3.football.api-sports.io")
            continue

    print(f"[*] API-Football Player scraping finished for this run. Made {requests_made} requests.")

if __name__ == "__main__":
    scrape_api_players_routine()
