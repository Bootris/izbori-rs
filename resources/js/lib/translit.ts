/*
| Serbian Latin → Cyrillic. Done on the client so every data file is published
| once, in Latin, and the reader picks the script.
*/
const DIGRAPHS: Record<string, string> = {
    'Dž': 'Џ', 'DŽ': 'Џ', 'dž': 'џ',
    'Lj': 'Љ', 'LJ': 'Љ', 'lj': 'љ',
    'Nj': 'Њ', 'NJ': 'Њ', 'nj': 'њ',
};

const SINGLE: Record<string, string> = {
    A: 'А', B: 'Б', V: 'В', G: 'Г', D: 'Д', Đ: 'Ђ', E: 'Е', Ž: 'Ж', Z: 'З', I: 'И', J: 'Ј', K: 'К', L: 'Л',
    M: 'М', N: 'Н', O: 'О', P: 'П', R: 'Р', S: 'С', T: 'Т', Ć: 'Ћ', U: 'У', F: 'Ф', H: 'Х', C: 'Ц', Č: 'Ч', Š: 'Ш',
    a: 'а', b: 'б', v: 'в', g: 'г', d: 'д', đ: 'ђ', e: 'е', ž: 'ж', z: 'з', i: 'и', j: 'ј', k: 'к', l: 'л',
    m: 'м', n: 'н', o: 'о', p: 'п', r: 'р', s: 'с', t: 'т', ć: 'ћ', u: 'у', f: 'ф', h: 'х', c: 'ц', č: 'ч', š: 'ш',
};

export function transliterate(input: string): string {
    let out = '';
    for (let i = 0; i < input.length; i++) {
        const pair = input.slice(i, i + 2);
        if (DIGRAPHS[pair] !== undefined) {
            out += DIGRAPHS[pair];
            i++;
            continue;
        }
        const ch = input[i] ?? '';
        out += SINGLE[ch] ?? ch;
    }
    return out;
}
