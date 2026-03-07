### Fork de master para feature branches sin afectar el proyecto original (CHATGPT--v9--2026-02-15--)

#### Concepto

Un fork crea una copia independiente del repositorio bajo tu cuenta. Cualquier rama o cambio que hagas en tu fork no afecta al repositorio original hasta que abras un Pull Request y los maintainers lo acepten.

#### Estructura del flujo

```
dokuwiki/dokuwiki  (repo original)
        |
        fork
        |
        v
tu-usuario/dokuwiki  (tu fork)
        |
        |-- feature/mi-nueva-funcionalidad
        |-- feature/otro-cambio
        |-- fix/bug-que-encontre
```

#### Pasos

- Hacer fork desde GitHub con el boton Fork en el repo original
- Clonar tu fork localmente

```bash
git clone https://github.com/tu-usuario/dokuwiki.git
cd dokuwiki
```

- Agregar el repo original como remote upstream

```bash
git remote add upstream https://github.com/dokuwiki/dokuwiki.git
```

- Sincronizar y crear tu feature branch

```bash
git checkout master
git pull upstream master
git checkout -b feature/mi-feature
```

- Publicar en tu fork

```bash
git push origin feature/mi-feature
```

#### Garantias de aislamiento

- El fork es completamente independiente del repo original
- Push a tu fork no afecta al proyecto original
- Puedes crear, borrar y romper ramas libremente
- El proyecto original solo se notifica si abres un Pull Request
- Los maintainers deciden si aceptan o rechazan el PR
