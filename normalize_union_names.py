import pymysql


DB = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "hums",
    "charset": "utf8mb4",
    "autocommit": False,
}


MAPPINGS = {
    "বুরিরচর": "বুড়িশ্চর",
    "ছিবাতলি": "ছিপাতলী",
    "গুমান মর্দন": "গুমানমর্দন",
}


def main():
    conn = pymysql.connect(**DB)
    cur = conn.cursor()

    changed = 0
    for old, new in MAPPINGS.items():
        cur.execute("UPDATE beneficiaries SET union_name=%s WHERE union_name=%s", (new, old))
        changed += cur.rowcount
        cur.execute("UPDATE institutions SET union_name=%s WHERE union_name=%s", (new, old))
        changed += cur.rowcount
        cur.execute("UPDATE users SET union_name=%s WHERE union_name=%s", (new, old))
        changed += cur.rowcount

    # Sync unions table to actual values used in beneficiaries
    cur.execute("DELETE FROM unions")
    cur.execute(
        "INSERT INTO unions(name) "
        "SELECT DISTINCT union_name FROM beneficiaries "
        "WHERE union_name IS NOT NULL AND union_name<>'' "
        "ORDER BY union_name"
    )

    conn.commit()

    cur.execute("SELECT COUNT(DISTINCT union_name) FROM beneficiaries")
    distinct_unions = int(cur.fetchone()[0] or 0)

    cur.close()
    conn.close()
    print(f"Normalization done. Updated rows={changed}, distinct unions now={distinct_unions}")


if __name__ == "__main__":
    main()

