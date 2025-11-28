import mysql.connector

# --- GANTI INFORMASI DI BAWAH INI ---
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'silabkom_db'
}
# ------------------------------------

try:
    # Membuat koneksi ke database
    connection = mysql.connector.connect(**db_config)

    if connection.is_connected():
        cursor = connection.cursor()
        
        # 1. Query untuk mendapatkan daftar tabel
        cursor.execute("SHOW TABLES;")
        tables = cursor.fetchall()
        
        print(f"Struktur database '{db_config['database']}':\n")

        if not tables:
            print("Database ini tidak memiliki tabel.")
        else:
            # 2. Looping untuk setiap tabel
            for table_tuple in tables:
                table_name = table_tuple[0]
                print(f"======================================================")
                print(f" Tabel: {table_name}")
                print(f"======================================================")
                
                # Query untuk mendapatkan struktur kolom dari tabel tersebut
                cursor.execute(f"DESCRIBE {table_name};")
                columns = cursor.fetchall()
                
                # Menampilkan header kolom
                print(f"{'Kolom':<25} {'Tipe Data':<20} {'Boleh Kosong?':<15} {'Kunci':<10}")
                print("-" * 75)
                
                # Menampilkan detail setiap kolom
                for column in columns:
                    # column[0] = Field (nama kolom)
                    # column[1] = Type (tipe data)
                    # column[2] = Null (apakah boleh null)
                    # column[3] = Key (apakah ini primary key, dll)
                    print(f"{column[0]:<25} {column[1]:<20} {column[2]:<15} {column[3]:<10}")
                print() # Menambah baris kosong untuk pemisah

except mysql.connector.Error as e:
    print(f"Error saat terhubung ke MySQL: {e}")

finally:
    # Pastikan koneksi ditutup
    if 'connection' in locals() and connection.is_connected():
        cursor.close()
        connection.close()
        print("\nKoneksi ke database ditutup.")
