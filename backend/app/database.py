from dotenv import load_dotenv
import os
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.orm import DeclarativeBase
load_dotenv()

SQLALCHEMY_DATABASE_URL = os.environ["DATABASE_URL"]

engine = create_engine(SQLALCHEMY_DATABASE_URL, echo=True) #prepara le connessioni al db 

SessionLocal = sessionmaker(bind=engine) #avvia la connessione con il database, echo serve a vedere le query sql nel terminale

 #permette a sqlalchemy di tenere un registro di tutte le tabelle esistenti nell'applicazione
class Base(DeclarativeBase):
    pass

def get_db():
    db = SessionLocal() #crea una nuova sessione
    try:
        yield db #presta la sessione a chi la richiede
    finally:
        db.close() #chiude la session
