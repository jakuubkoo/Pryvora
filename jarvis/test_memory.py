"""Self-check for the parts of memory.py that can silently do the wrong thing.

Run: python test_memory.py

Only the long-term half — the Redis half needs a live Redis and is covered by
the end-to-end curl checks instead.
"""

import memory


def test_whitelist_blocks_traversal():
    for bad in ("../../backend/.env", "log", "../user", "JARVIS_MEMORY"):
        try:
            memory.append_fact(bad, "should never be written")
        except ValueError:
            continue

        raise AssertionError(f"{bad!r} was accepted as a memory file")


def test_append_does_not_truncate(tmp):
    before = (memory.MEMORY_DIR / "preferences.md").read_text(encoding="utf-8")
    memory.append_fact("preferences", tmp)
    after = (memory.MEMORY_DIR / "preferences.md").read_text(encoding="utf-8")

    assert after.startswith(before), "append_fact rewrote the file instead of appending"
    assert tmp in after, "the fact was not written"


def test_duplicates_are_not_appended(tmp):
    memory.append_fact("preferences", tmp)
    text = (memory.MEMORY_DIR / "preferences.md").read_text(encoding="utf-8")

    assert text.count(tmp) == 1, "the same fact was stored twice"


def test_empty_fact_rejected():
    for bad in ("", "   ", "-", "- "):
        try:
            memory.append_fact("preferences", bad)
        except ValueError:
            continue

        raise AssertionError(f"empty fact {bad!r} was accepted")


def test_log_is_not_injected():
    (memory.MEMORY_DIR / "log.md").open("a", encoding="utf-8").write("\n- CANARY_LOG_LINE")
    assert "CANARY_LOG_LINE" not in memory.load(), "log.md leaked into the prompt"
    assert "Pryvora" in memory.load(), "projects.md is missing from the prompt"


def main():
    marker = "TEST CANARY do not keep"

    test_whitelist_blocks_traversal()
    test_append_does_not_truncate(marker)
    test_duplicates_are_not_appended(marker)
    test_empty_fact_rejected()
    test_log_is_not_injected()

    # Leave the files as we found them.
    path = memory.MEMORY_DIR / "preferences.md"
    path.write_text(
        "".join(line for line in path.read_text(encoding="utf-8").splitlines(keepends=True) if marker not in line),
        encoding="utf-8",
    )
    log = memory.MEMORY_DIR / "log.md"
    log.write_text(
        "".join(line for line in log.read_text(encoding="utf-8").splitlines(keepends=True) if "CANARY" not in line),
        encoding="utf-8",
    )

    print("memory self-check OK")


if __name__ == "__main__":
    main()
