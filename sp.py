import json
import time

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options


TT_FILE = "filtreli_imdb.txt"
OUTPUT_JSON = "movies.json"


def parse_json_ld(driver):
    scripts = driver.find_elements(By.CSS_SELECTOR, "script[type='application/ld+json']")
    for s in scripts:
        try:
            data = json.loads(s.get_attribute("innerHTML"))
            if isinstance(data, dict) and data.get("@type") == "Movie":
                return data
            if isinstance(data, list):
                for item in data:
                    if isinstance(item, dict) and item.get("@type") == "Movie":
                        return item
        except:
            pass
    return {}


def get_text(driver, selector):
    try:
        return driver.find_element(By.CSS_SELECTOR, selector).text.strip()
    except:
        return None


def extract_reviews(driver, limit=5):
    reviews = []

    titles = driver.find_elements(By.CSS_SELECTOR, "[data-testid='review-summary']")
    bodies = driver.find_elements(By.CSS_SELECTOR, "[data-testid='review-text']")

    if not bodies:
        bodies = driver.find_elements(By.CSS_SELECTOR, ".ipc-html-content-inner-div")

    for i in range(max(len(titles), len(bodies))):
        title = titles[i].text.strip() if i < len(titles) else None
        body = bodies[i].text.strip() if i < len(bodies) else None

        if title or body:
            reviews.append({
                "title": title,
                "body": body
            })

        if len(reviews) >= limit:
            break

    return reviews


def clean_amazon_image_url(url):
    if not url:
        return None

    try:
        if "._V1_" in url:
            base = url.split("._V1_")[0]
            return base + "._V1_.jpg"
        return url
    except:
        return url


def get_scenes(driver, limit=6):
    out = []

    imgs = driver.find_elements(
        By.CSS_SELECTOR,
        "a[data-testid^='mosaic-img-'] img.ipc-image"
    )

    if not imgs:
        imgs = driver.find_elements(
            By.CSS_SELECTOR,
            "section.ipc-page-section img.ipc-image"
        )

    for img in imgs:
        src = img.get_attribute("src")
        if not src:
            continue

        if "m.media-amazon.com" not in src:
            continue

        clean_src = clean_amazon_image_url(src)

        if clean_src and clean_src not in out:
            out.append(clean_src)

        if len(out) >= limit:
            break

    return out


def get_movie(driver, tt):
    url = f"https://www.imdb.com/title/{tt}/"
    driver.get(url)
    time.sleep(4)

    ld = parse_json_ld(driver)

    title = ld.get("name") or get_text(driver, "h1")
    summary = ld.get("description")

    year = None
    if ld.get("datePublished"):
        year = ld["datePublished"][:4]

    genres = ld.get("genre")
    if isinstance(genres, str):
        genres = [genres]

    rating = None
    if ld.get("aggregateRating"):
        rating = ld["aggregateRating"].get("ratingValue")

    poster = ld.get("image")
    reviews = extract_reviews(driver)
    scenes = get_scenes(driver)

    return {
        "tt": tt,
        "title": title,
        "summary": summary,
        "year": year,
        "genres": genres,
        "rating": rating,
        "poster": poster,
        "scenes": scenes,
        "reviews": reviews
    }


def main():
    with open(TT_FILE, "r", encoding="utf-8") as f:
        tts = [x.strip() for x in f if x.strip()]

    options = Options()
    options.add_argument("--window-size=1400,1000")
    options.add_argument("--disable-blink-features=AutomationControlled")

    driver = webdriver.Chrome(options=options)

    results = []

    try:
        total = len(tts)

        for i, tt in enumerate(tts, 1):
            print(f"{i}/{total} -> {tt}")

            try:
                movie = get_movie(driver, tt)
                results.append(movie)
            except Exception as e:
                print("HATA:", tt, e)

            # 🔥 Her 10 filmde bir kaydet
            if i % 10 == 0:
                with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
                    json.dump(results, f, ensure_ascii=False, indent=2)
                print(f"💾 Kaydedildi ({i} film)")

        # 🔥 Final kayıt
        with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
            json.dump(results, f, ensure_ascii=False, indent=2)

        print("✅ Tamamlandı -> movies.json")

    finally:
        driver.quit()


if __name__ == "__main__":
    main()