from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import os
import requests

app = FastAPI()

SERPAPI_KEY = os.getenv("SERPAPI_KEY")

class LookupRequest(BaseModel):
    query: str

@app.post("/search")
def search_linkedin(req: LookupRequest):
    if not SERPAPI_KEY:
        raise HTTPException(status_code=500, detail="SERPAPI_KEY not configured")
    params = {
        "engine": "google",
        "q": f"site:linkedin.com/in {req.query}",
        "api_key": SERPAPI_KEY,
    }
    resp = requests.get("https://serpapi.com/search", params=params, timeout=30)
    resp.raise_for_status()
    return resp.json()
