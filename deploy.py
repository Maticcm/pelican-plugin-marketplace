from pathlib import Path
import hashlib
import posixpath
import subprocess

LOCAL = Path(r"C:\Users\Matic\Documents\Pelican Plugin Marketplace\plugin-marketplace")

REMOTE = "matic@100.109.98.48"
REMOTE_DIR = "/var/www/pelican/plugins/plugin-marketplace"

changed = []
deleted = []


def sha256(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        while True:
            data = f.read(1024 * 1024)
            if not data:
                break
            h.update(data)
    return h.hexdigest()


print("Reading remote manifest...")

result = subprocess.run(
    [
        "ssh",
        REMOTE,
        f"cd {REMOTE_DIR} && find . -type f -exec sha256sum {{}} \\;"
    ],
    capture_output=True,
    text=True,
)

remote = {}

for line in result.stdout.splitlines():
    checksum, filename = line.split(maxsplit=1)
    filename = filename[2:]  # remove ./
    remote[filename] = checksum

local = {}

for file in LOCAL.rglob("*"):
    if file.is_dir():
        continue

    rel = file.relative_to(LOCAL).as_posix()
    local[rel] = sha256(file)

for file, checksum in local.items():
    if remote.get(file) != checksum:
        changed.append(file)

for file in remote:
    if file not in local:
        deleted.append(file)

print(f"Changed: {len(changed)}")
print(f"Deleted: {len(deleted)}")

for file in changed:
    local_file = LOCAL / file

    print("Uploading", file)

    remote_parent = posixpath.dirname(posixpath.join(REMOTE_DIR, file))

    subprocess.run([
        "ssh",
        REMOTE,
        f"mkdir -p '{remote_parent}'"
    ], check=True)

    subprocess.run([
        "scp",
        str(local_file),
        f"{REMOTE}:{REMOTE_DIR}/{file}"
    ], check=True)

for file in deleted:
    print("Deleting", file)

    subprocess.run([
        "ssh",
        REMOTE,
        f"rm -f '{REMOTE_DIR}/{file}'"
    ], check=True)

commands = [
    "cd /var/www/pelican"
]

if "composer.json" in changed:
    commands.append("composer install")

if any(f.startswith("database/migrations") for f in changed):
    commands.append("php artisan migrate --force")

commands.append("php artisan optimize:clear")

if "plugin.json" in changed:
    commands.append("php artisan p:plugin:install plugin-marketplace")

print("Running remote commands...")

subprocess.run([
    "ssh",
    REMOTE,
    "\n".join(commands)
], check=True)

print("\nDeployment complete.")