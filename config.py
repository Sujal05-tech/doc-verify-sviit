import os

class Config:
    # Flask
    SECRET_KEY = 'sviit-doc-verify-secret-2026'

    # MySQL Database
    MYSQL_HOST = 'localhost'
    MYSQL_USER = 'root'
    MYSQL_PASSWORD = ''          # XAMPP mein default password blank hota hai
    MYSQL_DB = 'doc_verify_db'
    MYSQL_CURSORCLASS = 'DictCursor'

    # File Upload
    UPLOAD_FOLDER = 'static/uploads'
    MAX_CONTENT_LENGTH = 2 * 1024 * 1024    # 2MB max file size
    ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'pdf'}
