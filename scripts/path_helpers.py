from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def repo_path(*parts: str) -> Path:
    return ROOT.joinpath(*parts)


def data_path(*parts: str) -> Path:
    return ROOT.joinpath("data", *parts)


def imports_path(*parts: str) -> Path:
    return ROOT.joinpath("imports", *parts)


def taxonomy_path(*parts: str) -> Path:
    return ROOT.joinpath("transposon_tree", *parts)


def terminology_path(*parts: str) -> Path:
    return ROOT.joinpath("terminology", *parts)


def api_path(*parts: str) -> Path:
    return ROOT.joinpath("api", *parts)


def scripts_path(*parts: str) -> Path:
    return ROOT.joinpath("scripts", *parts)
