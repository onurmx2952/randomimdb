import pandas as pd

print("=== IMDb Dataset Filtreleme Başlıyor ===\n")

# -----------------------------------------------
# 1. title.ratings.tsv.gz
# -----------------------------------------------
print("[1/3] title.ratings.tsv.gz okunuyor...")

ratings = pd.read_csv(
    "title.ratings.tsv.gz",
    sep="\t",
    compression="gzip",
    dtype={"tconst": str},
    low_memory=False,
)

ratings = ratings[
    (ratings["averageRating"] > 6.7) &
    (ratings["numVotes"] > 5000)
]

good_tt_ratings = set(ratings["tconst"])
print(f"  → Ratings filtresi sonrası: {len(good_tt_ratings):,} film\n")

# -----------------------------------------------
# 2. title.basics.tsv.gz
# -----------------------------------------------
print("[2/3] title.basics.tsv.gz okunuyor...")

basics = pd.read_csv(
    "title.basics.tsv.gz",
    sep="\t",
    compression="gzip",
    dtype={"tconst": str, "startYear": str, "genres": str},
    low_memory=False,
    na_values=["\\N"],
)

basics = basics[basics["titleType"] == "movie"]
basics["startYear"] = pd.to_numeric(basics["startYear"], errors="coerce")
basics = basics[basics["startYear"] > 1999]
basics = basics[
    basics["genres"].isna() |
    (~basics["genres"].str.contains("Documentary", na=False))
]

good_tt_basics = set(basics["tconst"])
print(f"  → Basics filtresi sonrası: {len(good_tt_basics):,} film\n")

# -----------------------------------------------
# 3. title.akas.tsv.gz → Hint yapımı filmleri dışla
#
# Kural 1: ilk iki ordering'de \N → IN sırası varsa Hint filmi
# Kural 2: region=IN birden fazla kez geçiyorsa Hint filmi
# -----------------------------------------------
print("[3/3] title.akas.tsv.gz okunuyor (büyük dosya, biraz sürebilir)...")

first_two = []
in_counts = {}  # tt → kaç tane IN region satırı var

akas_iter = pd.read_csv(
    "title.akas.tsv.gz",
    sep="\t",
    compression="gzip",
    dtype=str,
    low_memory=False,
    na_values=[],
    chunksize=500_000,
)

for chunk in akas_iter:
    chunk["ordering"] = pd.to_numeric(chunk["ordering"], errors="coerce")

    # Kural 2: IN sayısını say
    in_rows = chunk[chunk["region"] == "IN"].groupby("titleId").size()
    for tt, cnt in in_rows.items():
        in_counts[tt] = in_counts.get(tt, 0) + cnt

    # Kural 1: ilk 2 ordering'i topla
    chunk_sorted = chunk.sort_values(["titleId", "ordering"])
    top2 = chunk_sorted.groupby("titleId").head(2)
    first_two.append(top2[["titleId", "ordering", "region"]])

akas_top2 = pd.concat(first_two, ignore_index=True)
akas_top2 = akas_top2.sort_values(["titleId", "ordering"])
akas_top2 = akas_top2.groupby("titleId").head(2).reset_index(drop=True)
akas_top2["rank"] = akas_top2.groupby("titleId").cumcount() + 1

rank1 = akas_top2[akas_top2["rank"] == 1].set_index("titleId")["region"]
rank2 = akas_top2[akas_top2["rank"] == 2].set_index("titleId")["region"]

common = rank1.index.intersection(rank2.index)
hindi_rule1 = set(
    common[
        (rank1.loc[common] == "\\N") &
        (rank2.loc[common] == "IN")
    ]
)

hindi_rule2 = {tt for tt, cnt in in_counts.items() if cnt > 1}

hindi_tt = hindi_rule1 | hindi_rule2

print(f"  → Kural 1 (\\N→IN): {len(hindi_rule1):,} film")
print(f"  → Kural 2 (birden fazla IN): {len(hindi_rule2):,} film")
print(f"  → Toplam Hint yapımı: {len(hindi_tt):,} film\n")

# -----------------------------------------------
# 4. Tüm filtreleri birleştir
# -----------------------------------------------
print("Filtreler birleştiriliyor...")

final_tt = (good_tt_ratings & good_tt_basics) - hindi_tt

print(f"  → SONUÇ: Toplam {len(final_tt):,} film kaldı\n")

# -----------------------------------------------
# 5. Sonucu kaydet (sadece tt numaraları)
# -----------------------------------------------
output_file = "filtreli_imdb.txt"
with open(output_file, "w") as f:
    f.write("\n".join(sorted(final_tt)))

print(f"Kayıt tamamlandı: {output_file}")
print(f"Toplam tt sayısı: {len(final_tt):,}")
print("\n=== Bitti ===")
