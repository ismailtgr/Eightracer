"""Offline relevance evaluation. Input contains pseudonymous query IDs and eligible items only."""
import argparse
import collections
import csv
import json
import math
import pathlib


def evaluate(rows, k=5):
    if k < 1 or k > 100:
        raise ValueError('k harus 1 sampai 100.')
    groups = collections.defaultdict(list)
    for i, raw in enumerate(rows, 2):
        try:
            q = raw['query_id'].strip()
            item = int(raw['beasiswa_id'])
            relevance = int(raw['relevance'])
            baseline = int(raw['baseline_rank'])
            ml = int(raw['ml_rank'])
            if not q or item < 1 or relevance not in range(4) or min(baseline, ml) < 1:
                raise ValueError()
            groups[q].append(dict(item=item, relevance=relevance, baseline=baseline, ml=ml))
        except (KeyError, TypeError, ValueError):
            raise ValueError(f'Baris {i} tidak valid; periksa format dan nilai.') from None
    if not groups:
        return dict(status='awaiting_labels', queries=0, k=k, baseline=None, ml=None,
                    note='Belum ada label relevansi; efektivitas ML belum dapat dihitung.')
    values = {'baseline': [], 'ml': []}
    no_positive = short = 0
    for items in groups.values():
        n = len(items)
        if len({x['item'] for x in items}) != n:
            raise ValueError('Ada pasangan query-beasiswa berulang.')
        for algorithm in values:
            if {x[algorithm] for x in items} != set(range(1, n + 1)):
                raise ValueError('Urutan harus unik dan berurutan 1 sampai jumlah kandidat per query.')
        if n < 2:
            raise ValueError('Setiap query evaluasi memerlukan minimal dua kandidat yang dinilai.')
        ideal = sorted((x['relevance'] for x in items), reverse=True)[:k]
        idcg = sum(v / math.log2(i + 2) for i, v in enumerate(ideal))
        no_positive += idcg == 0
        short += n < k
        for algorithm in values:
            ranked = sorted(items, key=lambda x: x[algorithm])[:k]
            dcg = sum(x['relevance'] / math.log2(i + 2) for i, x in enumerate(ranked))
            precision = sum(x['relevance'] >= 2 for x in ranked) / k
            values[algorithm].append((precision, dcg / idcg if idcg else None))
    result = dict(status='evaluated', queries=len(groups), k=k, queries_without_positive_relevance=no_positive,
                  queries_with_fewer_than_k_candidates=short,
                  note='Kandidat yang sama, sudah eligible. Precision memakai penyebut k; NDCG mengabaikan query tanpa relevansi positif. Ini relevansi menurut label, bukan peluang lolos.')
    for algorithm, samples in values.items():
        ndcg = [x[1] for x in samples if x[1] is not None]
        result[algorithm] = dict(precision_at_k=round(sum(x[0] for x in samples) / len(samples), 6),
                                 ndcg_at_k=round(sum(ndcg) / len(ndcg), 6) if ndcg else None)
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('csv', type=pathlib.Path)
    parser.add_argument('--k', type=int, default=5)
    args = parser.parse_args()
    try:
        with args.csv.open(encoding='utf-8-sig', newline='') as stream:
            result = evaluate(list(csv.DictReader(stream)), args.k)
    except (ValueError, OSError) as error:
        message = str(error) if isinstance(error, ValueError) else 'Berkas evaluasi belum dapat dibaca.'
        print(json.dumps(dict(status='invalid_input', message=message), ensure_ascii=False))
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result['status'] == 'evaluated' else 2


if __name__ == '__main__':
    raise SystemExit(main())
