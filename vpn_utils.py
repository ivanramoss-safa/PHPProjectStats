"""
Shared VPN rotation utility for all BeSoccer/API scrapers.
Uses WireGuard CLI with the .conf files in the VPN/ folder.
Rotate when you detect a 403/429/503 block from any server.
"""
import os, subprocess, time, urllib.request
from urllib.error import HTTPError

VPN_DIR = os.path.join(os.path.dirname(__file__), "VPN")
_vpn_files = [f for f in os.listdir(VPN_DIR) if f.endswith('.conf')]
_current_vpn_idx = [-1]  # mutable container so all importers share the same index

def rotate_vpn():
    """Disconnect the current WireGuard tunnel and connect to the next one."""
    idx = _current_vpn_idx[0]
    if idx >= 0:
        old_vpn = _vpn_files[idx].replace('.conf', '')
        print(f"[VPN] Disconnecting {old_vpn}...")
        subprocess.run(["wireguard", "/uninstalltunnelservice", old_vpn], capture_output=True)
        time.sleep(3)

    idx = (idx + 1) % len(_vpn_files)
    _current_vpn_idx[0] = idx
    new_vpn_name = _vpn_files[idx]
    vpn_path = os.path.join(VPN_DIR, new_vpn_name)
    print(f"[VPN] Connecting to {new_vpn_name}...")
    subprocess.run(["wireguard", "/installtunnelservice", vpn_path], capture_output=True)
    print("[VPN] Waiting 10s for handshake...")
    time.sleep(10)

def robust_get(url, retries=3, extra_headers=None):
    """
    Perform an HTTP GET with standard browser headers.
    Automatically rotates VPN on 403/429/503.
    Returns the decoded response string, or None on failure.
    """
    headers = {
        'User-Agent': (
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            'AppleWebKit/537.36 (KHTML, like Gecko) '
            'Chrome/122.0.0.0 Safari/537.36'
        ),
        'Accept-Language': 'es-ES,es;q=0.9,en;q=0.8',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    }
    if extra_headers:
        headers.update(extra_headers)

    for attempt in range(retries):
        req = urllib.request.Request(url, headers=headers)
        try:
            resp = urllib.request.urlopen(req, timeout=12)
            return resp.read().decode('utf-8')
        except HTTPError as e:
            if e.code in (403, 429, 503):
                print(f"[VPN] Blocked ({e.code}) on {url} — rotating VPN...")
                rotate_vpn()
            else:
                print(f"[!] HTTP {e.code} on {url}")
                return None
        except Exception as e:
            wait = 3 * (attempt + 1)
            print(f"[!] Network error: {e}. Retrying in {wait}s ({attempt+1}/{retries})...")
            time.sleep(wait)
    return None
