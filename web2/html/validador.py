#!/usr/bin/env python3
import sys
import re
import pyotp

def main():
    # Validar que se reciban exactamente los argumentos esperados
    if len(sys.argv) != 3:
        print("False")
        sys.exit(1)

    secret = sys.argv[1].strip()
    code = sys.argv[2].strip()

    # Validación estricta de formato (Base32 para el secret, 6 dígitos para el código)
    if not re.match(r'^[A-Z2-7=]{16,32}$', secret, re.IGNORECASE) or not re.match(r'^\d{6}$', code):
        print("False")
        sys.exit(1)

    try:
        # Inicializar TOTP con la librería pyotp
        totp = pyotp.TOTP(secret)

        # Verificar el código proporcionado con ventana de tolerancia (valid_window=1)
        if totp.verify(code, valid_window=1):
            print("True")
            sys.exit(0)
        else:
            print("False")
            sys.exit(1)
            
    except Exception as e:
        # Silenciamos el error para no romper la lectura del exec en PHP
        print("False")
        sys.exit(1)

if __name__ == '__main__':
    main()
