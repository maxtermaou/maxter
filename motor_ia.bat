@echo off
:: Estas dos líneas obligan a la terminal oculta de Windows a usar UTF-8 (para soportar emojis y tildes)
chcp 65001 > nul
set PYTHONIOENCODING=utf-8

echo Iniciando el Cerebro de IA de Talento Humano - UNEFA...

"C:\Users\wilfr\AppData\Local\Programs\Python\Python314\python.exe" detector_ausencias.py
"C:\Users\wilfr\AppData\Local\Programs\Python\Python314\python.exe" analisis_ia.py
"C:\Users\wilfr\AppData\Local\Programs\Python\Python314\python.exe" ia_patrones.py

echo.
echo Análisis completado con éxito.