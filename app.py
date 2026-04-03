from flask import Flask, render_template
from config import Config
from modules.bus_pass.routes import bus_pass

app = Flask(__name__)
app.config.from_object(Config)

app.register_blueprint(bus_pass, url_prefix='/buspass')

@app.route('/')
def home():
    return render_template('home.html')

if __name__ == '__main__':
    app.run(debug=True)