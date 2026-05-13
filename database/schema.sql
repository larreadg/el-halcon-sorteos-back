PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS cliente (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    nombres         TEXT    NOT NULL,
    apellidos       TEXT    NOT NULL,
    documento       TEXT    NOT NULL UNIQUE,
    telefono        TEXT,
    ciudad          TEXT,
    fecha_creacion      TEXT NOT NULL,
    fecha_actualizacion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cupon (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id      INTEGER NOT NULL REFERENCES cliente(id),
    monto_compra    REAL    NOT NULL CHECK (monto_compra > 0),
    cantidad_cupon  INTEGER NOT NULL CHECK (cantidad_cupon > 0),
    fecha_creacion  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS parametro (
    clave    TEXT PRIMARY KEY,
    valor    TEXT NOT NULL,
    tipo     TEXT NOT NULL CHECK (tipo IN ('integer', 'real', 'text'))
);

CREATE TABLE IF NOT EXISTS ciudad (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ciudad          TEXT    NOT NULL,
    departamento    TEXT    NOT NULL
);

INSERT OR IGNORE INTO parametro (clave, valor, tipo) VALUES
    ('cupones_por_regla', '1',   'integer'),
    ('monto_por_regla',   '20000', 'real');
