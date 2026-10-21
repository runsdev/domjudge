#!/usr/bin/env python3
"""A toy contest to test DOMjudge with: one problem per topic of an introductory C++ course,
as two contests with the same problems and test data, in English (toy-en) and in Indonesian
(toy-id). DOMjudge has one statement per problem, so each contest gets its own copy.

    ./make.py                     writes en/ and id/ with contest.yaml, problems.yaml, the
                                  problem directories and their zips
    ./make.py URL USER:PASSWORD   also imports both contests, e.g.
                                  ./make.py http://localhost:12345 admin:secret

The jury solutions in the problems are submitted on import when the user has a team, and the
judging verifier shows whether they got the verdict of their directory.
"""
import json
import shutil
import subprocess
import sys
import zipfile
from inspect import cleandoc
from math import isqrt
from pathlib import Path


def lines(*values):
    return ''.join(f'{v}\n' for v in values)


def is_prime(n):
    return n > 1 and all(n % d for d in range(2, isqrt(n) + 1))


def collatz_steps(c):
    steps = 0
    while c != 1:
        c = c // 2 if c % 2 == 0 else 3 * c + 1
        steps += 1
    return steps


def numbers(ns):
    return lines(len(ns), *ns)


# Primes around the square root of 10^9, for squares and products of two primes.
ROOT_PRIMES = [p for p in range(31400, 31623) if is_prime(p)]

CONTESTS = {
    'en': 'Toy Contest (EN)',
    'id': 'Kontes Latihan (ID)',
}

PROBLEMS = [
    {
        'id': 'hello', 'label': 'A', 'rgb': '#E53935',
        'topic': {'en': 'Topic 1 – Intro to Programming', 'id': 'Topik 1 – Pengantar Pemrograman'},
        'name': {'en': 'Hello, World!', 'id': 'Hello, World!'},
        'statement': {
            'en': r"""
                Write a program that prints `Hello, World!`.

                ## Input

                There is no input.

                ## Output

                One line with the text `Hello, World!`.
            """,
            'id': r"""
                Buatlah program yang mencetak `Hello, World!`.

                ## Masukan

                Tidak ada masukan.

                ## Keluaran

                Satu baris berisi teks `Hello, World!`.
            """,
        },
        'solve': lambda inp: lines('Hello, World!'),
        'sample': [''],
        'secret': [],
        'submissions': {
            'accepted/hello.cpp': r"""
                #include <iostream>

                int main() {
                    std::cout << "Hello, World!" << std::endl;
                }
            """,
            'accepted/hello.py': r"""
                print("Hello, World!")
            """,
            'wrong_answer/hello-no-comma.cpp': r"""
                #include <iostream>

                int main() {
                    std::cout << "Hello World!" << std::endl;
                }
            """,
        },
    },
    {
        'id': 'arith', 'label': 'B', 'rgb': '#FB8C00',
        'topic': {'en': 'Topic 2 – Intro to C++', 'id': 'Topik 2 – Pengantar C++'},
        'name': {'en': 'Arithmetic Operators', 'id': 'Operator Aritmetika'},
        'statement': {
            'en': r"""
                Given two integers $a$ and $b$, compute their sum, difference and product, and the
                quotient and remainder of dividing $a$ by $b$.

                ## Input

                One line with two integers $a$ and $b$ ($1 \le a, b \le 1000$).

                ## Output

                Five lines with $a + b$, $a - b$, $a \times b$, the quotient $a / b$ rounded down, and
                the remainder $a \bmod b$.
            """,
            'id': r"""
                Diberikan dua bilangan bulat $a$ dan $b$. Hitunglah jumlah, selisih, dan hasil kali
                keduanya, serta hasil bagi dan sisa pembagian $a$ oleh $b$.

                ## Masukan

                Satu baris berisi dua bilangan bulat $a$ dan $b$ ($1 \le a, b \le 1000$).

                ## Keluaran

                Lima baris berisi $a + b$, $a - b$, $a \times b$, hasil bagi $a / b$ yang dibulatkan ke
                bawah, dan sisa pembagian $a \bmod b$.
            """,
        },
        'solve': lambda inp: (lambda a, b: lines(a + b, a - b, a * b, a // b, a % b))(*map(int, inp.split())),
        'sample': ['7 3', '5 10'],
        'secret': ['1 1', '1000 1000', '1000 1', '1 1000', '999 7', '123 45'],
        'submissions': {
            'accepted/arith.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int a, b;
                    cin >> a >> b;
                    cout << a + b << '\n' << a - b << '\n' << a * b << '\n' << a / b << '\n' << a % b << '\n';
                }
            """,
            'wrong_answer/arith-real-division.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int a, b;
                    cin >> a >> b;
                    cout << a + b << '\n' << a - b << '\n' << a * b << '\n' << (double)a / b << '\n' << a % b << '\n';
                }
            """,
            'run_time_error/arith-exit-code.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int a, b;
                    cin >> a >> b;
                    cout << a + b << '\n' << a - b << '\n' << a * b << '\n' << a / b << '\n' << a % b << '\n';
                    return 1;
                }
            """,
        },
    },
    {
        'id': 'celsius', 'label': 'C', 'rgb': '#FDD835', 'validator_flags': 'float_tolerance 1E-6',
        'topic': {'en': 'Topic 3 – Writing a Program', 'id': 'Topik 3 – Menulis Program'},
        'name': {'en': 'Celsius to Fahrenheit', 'id': 'Celsius ke Fahrenheit'},
        'statement': {
            'en': r"""
                Convert a temperature in degrees Celsius to degrees Fahrenheit:

                $$F = \frac{9}{5} C + 32$$

                ## Input

                One line with an integer $C$ ($-273 \le C \le 1000$), the temperature in degrees Celsius.

                ## Output

                One line with the temperature $F$ in degrees Fahrenheit. Your answer may differ by at
                most $10^{-6}$ from the correct one, absolute or relative.
            """,
            'id': r"""
                Ubahlah suhu dalam derajat Celsius menjadi derajat Fahrenheit:

                $$F = \frac{9}{5} C + 32$$

                ## Masukan

                Satu baris berisi bilangan bulat $C$ ($-273 \le C \le 1000$), yaitu suhu dalam derajat
                Celsius.

                ## Keluaran

                Satu baris berisi suhu $F$ dalam derajat Fahrenheit. Jawaban dianggap benar jika
                selisih mutlak atau relatifnya dengan jawaban yang tepat paling besar $10^{-6}$.
            """,
        },
        'solve': lambda inp: lines(f'{int(inp) * 9 / 5 + 32:g}'),
        'sample': ['100', '37'],
        'secret': ['0', '-40', '-273', '1000', '1', '-17'],
        'submissions': {
            'accepted/celsius.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int c;
                    cin >> c;
                    double f = 9.0 / 5.0 * c + 32;
                    cout << f << endl;
                }
            """,
            'wrong_answer/celsius-integer-division.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int c;
                    cin >> c;
                    int f = c * 9 / 5 + 32;
                    cout << f << endl;
                }
            """,
        },
    },
    {
        'id': 'grade', 'label': 'D', 'rgb': '#43A047',
        'topic': {'en': 'Topic 4 – Selection', 'id': 'Topik 4 – Percabangan'},
        'name': {'en': 'Letter Grade', 'id': 'Nilai Huruf'},
        'statement': {
            'en': r"""
                A course turns scores into letter grades:

                - A for scores from 80 to 100,
                - B for scores from 70 to 79,
                - C for scores from 60 to 69,
                - D for scores from 50 to 59,
                - E for scores from 0 to 49.

                Print the letter grade of a score.

                ## Input

                One line with an integer $s$ ($0 \le s \le 100$), the score.

                ## Output

                One line with the letter grade.
            """,
            'id': r"""
                Sebuah mata kuliah mengubah nilai angka menjadi nilai huruf:

                - A untuk nilai 80 sampai 100,
                - B untuk nilai 70 sampai 79,
                - C untuk nilai 60 sampai 69,
                - D untuk nilai 50 sampai 59,
                - E untuk nilai 0 sampai 49.

                Cetaklah nilai huruf dari sebuah nilai angka.

                ## Masukan

                Satu baris berisi bilangan bulat $s$ ($0 \le s \le 100$), yaitu nilai angka.

                ## Keluaran

                Satu baris berisi nilai hurufnya.
            """,
        },
        'solve': lambda inp: lines(next(g for g, low in (('A', 80), ('B', 70), ('C', 60), ('D', 50), ('E', 0))
                                        if int(inp) >= low)),
        'sample': ['85', '42'],
        'secret': ['0', '49', '50', '59', '60', '69', '70', '79', '80', '100'],
        'submissions': {
            'accepted/grade.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int s;
                    cin >> s;
                    if (s >= 80) {
                        cout << "A" << endl;
                    } else if (s >= 70) {
                        cout << "B" << endl;
                    } else if (s >= 60) {
                        cout << "C" << endl;
                    } else if (s >= 50) {
                        cout << "D" << endl;
                    } else {
                        cout << "E" << endl;
                    }
                }
            """,
            'wrong_answer/grade-boundary.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int s;
                    cin >> s;
                    if (s > 80) {
                        cout << "A" << endl;
                    } else if (s > 70) {
                        cout << "B" << endl;
                    } else if (s > 60) {
                        cout << "C" << endl;
                    } else if (s > 50) {
                        cout << "D" << endl;
                    } else {
                        cout << "E" << endl;
                    }
                }
            """,
        },
    },
    {
        'id': 'fizzbuzz', 'label': 'E', 'rgb': '#1E88E5',
        'topic': {'en': 'Topic 4 – Looping and iteration', 'id': 'Topik 4 – Perulangan dan Iterasi'},
        'name': {'en': 'FizzBuzz', 'id': 'FizzBuzz'},
        'statement': {
            'en': r"""
                Print the integers from 1 to $n$, one per line. But print `Fizz` instead of a number
                divisible by 3, `Buzz` instead of a number divisible by 5, and `FizzBuzz` instead of a
                number divisible by both 3 and 5.

                ## Input

                One line with an integer $n$ ($1 \le n \le 100$).

                ## Output

                $n$ lines as described above.
            """,
            'id': r"""
                Cetaklah bilangan bulat dari 1 sampai $n$, satu per baris. Namun, cetak `Fizz` sebagai
                ganti bilangan yang habis dibagi 3, `Buzz` sebagai ganti bilangan yang habis dibagi 5,
                dan `FizzBuzz` sebagai ganti bilangan yang habis dibagi 3 dan 5.

                ## Masukan

                Satu baris berisi bilangan bulat $n$ ($1 \le n \le 100$).

                ## Keluaran

                $n$ baris sesuai aturan di atas.
            """,
        },
        'solve': lambda inp: lines(*('FizzBuzz' if i % 15 == 0 else 'Fizz' if i % 3 == 0 else 'Buzz' if i % 5 == 0
                                     else i for i in range(1, int(inp) + 1))),
        'sample': ['15'],
        'secret': ['1', '2', '3', '5', '30', '100'],
        'submissions': {
            'accepted/fizzbuzz.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int n;
                    cin >> n;
                    for (int i = 1; i <= n; i++) {
                        if (i % 15 == 0) {
                            cout << "FizzBuzz\n";
                        } else if (i % 3 == 0) {
                            cout << "Fizz\n";
                        } else if (i % 5 == 0) {
                            cout << "Buzz\n";
                        } else {
                            cout << i << "\n";
                        }
                    }
                }
            """,
            'wrong_answer/fizzbuzz-order.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    int n;
                    cin >> n;
                    for (int i = 1; i <= n; i++) {
                        if (i % 3 == 0) {
                            cout << "Fizz\n";
                        } else if (i % 5 == 0) {
                            cout << "Buzz\n";
                        } else if (i % 15 == 0) {
                            cout << "FizzBuzz\n";
                        } else {
                            cout << i << "\n";
                        }
                    }
                }
            """,
        },
    },
    {
        'id': 'collatz', 'label': 'F', 'rgb': '#8E24AA',
        'topic': {'en': 'Assignment as Netacad', 'id': 'Tugas ala Netacad'},
        'name': {'en': 'Collatz Conjecture', 'id': 'Dugaan Collatz'},
        'statement': {
            'en': r"""
                Start with a positive integer $c$. If $c$ is even, halve it; if $c$ is odd, replace it
                by $3c + 1$. Repeat this until $c$ is 1. The Collatz conjecture says that this always
                happens.

                Count the steps it takes to reach 1.

                ## Input

                One line with an integer $c$ ($1 \le c \le 10^5$).

                ## Output

                One line with the number of steps.
            """,
            'id': r"""
                Mulailah dari sebuah bilangan bulat positif $c$. Jika $c$ genap, bagilah dengan 2; jika
                $c$ ganjil, gantilah dengan $3c + 1$. Ulangi langkah ini sampai $c$ bernilai 1. Dugaan
                Collatz menyatakan bahwa hal ini selalu terjadi.

                Hitunglah banyaknya langkah sampai $c$ bernilai 1.

                ## Masukan

                Satu baris berisi bilangan bulat $c$ ($1 \le c \le 10^5$).

                ## Keluaran

                Satu baris berisi banyaknya langkah.
            """,
        },
        'solve': lambda inp: lines(collatz_steps(int(inp))),
        'sample': ['6', '1'],
        'secret': ['2', '7', '27', '97', '77031', '100000'],
        'submissions': {
            'accepted/collatz.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    long long c;
                    cin >> c;
                    int steps = 0;
                    while (c != 1) {
                        if (c % 2 == 0) {
                            c = c / 2;
                        } else {
                            c = 3 * c + 1;
                        }
                        steps++;
                    }
                    cout << steps << endl;
                }
            """,
            'wrong_answer/collatz-off-by-one.cpp': r"""
                #include <iostream>
                using namespace std;

                int main() {
                    long long c;
                    cin >> c;
                    int steps = 0;
                    do {
                        if (c % 2 == 0) {
                            c = c / 2;
                        } else {
                            c = 3 * c + 1;
                        }
                        steps++;
                    } while (c != 1);
                    cout << steps << endl;
                }
            """,
        },
    },
    {
        'id': 'prime', 'label': 'G', 'rgb': '#6D4C41',
        'topic': {'en': '9_Functions', 'id': '9_Fungsi'},
        'name': {'en': 'Prime Numbers', 'id': 'Bilangan Prima'},
        'statement': {
            'en': r"""
                A prime number is an integer greater than 1 that is divisible only by 1 and itself.
                Write a function that checks whether a number is prime, and use it to answer $t$
                questions.

                ## Input

                The first line has an integer $t$ ($1 \le t \le 100$). Each of the next $t$ lines has an
                integer $n$ ($1 \le n \le 10^9$).

                ## Output

                For each $n$, one line with `YES` if $n$ is prime and `NO` otherwise.
            """,
            'id': r"""
                Bilangan prima adalah bilangan bulat lebih dari 1 yang hanya habis dibagi oleh 1 dan
                dirinya sendiri. Buatlah sebuah fungsi yang memeriksa apakah suatu bilangan prima, lalu
                gunakan fungsi itu untuk menjawab $t$ pertanyaan.

                ## Masukan

                Baris pertama berisi bilangan bulat $t$ ($1 \le t \le 100$). Masing-masing dari $t$
                baris berikutnya berisi bilangan bulat $n$ ($1 \le n \le 10^9$).

                ## Keluaran

                Untuk setiap $n$, satu baris berisi `YES` jika $n$ prima, atau `NO` jika bukan.
            """,
        },
        'solve': lambda inp: lines(*('YES' if is_prime(n) else 'NO' for n in map(int, inp.split()[1:]))),
        'sample': [numbers([1, 2, 9, 17, 100]).strip()],
        'secret': [
            numbers(range(1, 101)).strip(),
            numbers([10**9 - k for k in range(100)]).strip(),
            numbers(([p * p for p in ROOT_PRIMES] + [p * q for p, q in zip(ROOT_PRIMES, ROOT_PRIMES[1:])]
                     + ROOT_PRIMES)[:100]).strip(),
        ],
        'submissions': {
            'accepted/prime.cpp': r"""
                #include <iostream>
                using namespace std;

                bool isPrime(int n) {
                    if (n < 2) {
                        return false;
                    }
                    for (long long d = 2; d * d <= n; d++) {
                        if (n % d == 0) {
                            return false;
                        }
                    }
                    return true;
                }

                int main() {
                    int t;
                    cin >> t;
                    while (t--) {
                        int n;
                        cin >> n;
                        cout << (isPrime(n) ? "YES" : "NO") << "\n";
                    }
                }
            """,
            'wrong_answer/prime-one.cpp': r"""
                #include <iostream>
                using namespace std;

                bool isPrime(int n) {
                    for (long long d = 2; d * d <= n; d++) {
                        if (n % d == 0) {
                            return false;
                        }
                    }
                    return true;
                }

                int main() {
                    int t;
                    cin >> t;
                    while (t--) {
                        int n;
                        cin >> n;
                        cout << (isPrime(n) ? "YES" : "NO") << "\n";
                    }
                }
            """,
            'time_limit_exceeded/prime-naive.cpp': r"""
                #include <iostream>
                using namespace std;

                bool isPrime(int n) {
                    if (n < 2) {
                        return false;
                    }
                    for (int d = 2; d < n; d++) {
                        if (n % d == 0) {
                            return false;
                        }
                    }
                    return true;
                }

                int main() {
                    int t;
                    cin >> t;
                    while (t--) {
                        int n;
                        cin >> n;
                        cout << (isPrime(n) ? "YES" : "NO") << "\n";
                    }
                }
            """,
        },
    },
]


def write(lang, base):
    shutil.rmtree(base, ignore_errors=True)
    base.mkdir()
    (base / 'contest.yaml').write_text(
        f"id: toy-{lang}\nname: {json.dumps(CONTESTS[lang])}\n"
        "start_time: 2026-10-21T00:00:00+07:00\nduration: 720:00:00\npenalty_time: 20\n")
    problems_yaml = ''
    for p in PROBLEMS:
        pid = f"{p['id']}-{lang}"
        problems_yaml += f"- id: {pid}\n  label: {p['label']}\n  rgb: '{p['rgb']}'\n"
        files = {
            'problem.yaml': f"name: {json.dumps(p['name'][lang])}\nlimits:\n  time_limit: 1\n"
                            + (f"validator_flags: {p['validator_flags']}\n" if 'validator_flags' in p else ''),
            'problem_statement/problem.md': f"*{p['topic'][lang]}*\n\n{cleandoc(p['statement'][lang])}\n",
        }
        for kind in ('sample', 'secret'):
            for i, inp in enumerate(p[kind], 1):
                inp = cleandoc(inp) + '\n' if inp else ''
                files[f'data/{kind}/{i:02}.in'] = inp
                files[f'data/{kind}/{i:02}.ans'] = p['solve'](inp)
        for path, source in p['submissions'].items():
            files[f'submissions/{path}'] = cleandoc(source) + '\n'
        with zipfile.ZipFile(base / f'{pid}.zip', 'w') as zip:
            for path, content in files.items():
                (base / pid / path).parent.mkdir(parents=True, exist_ok=True)
                (base / pid / path).write_text(content)
                zip.writestr(path, content)
    (base / 'problems.yaml').write_text(problems_yaml)


def post(url, auth, path, *fields):
    command = ['curl', '-sS', '--fail-with-body', '-u', auth, f'{url}/api/v4/{path}']
    for field in fields:
        command += ['-F', field]
    print(f'POST {path}:', subprocess.run(command, check=True, capture_output=True, text=True).stdout[:300])


def main():
    here = Path(__file__).resolve().parent
    for lang in CONTESTS:
        write(lang, here / lang)
    if len(sys.argv) == 3:
        url, auth = sys.argv[1].rstrip('/'), sys.argv[2]
        for lang in CONTESTS:
            base = here / lang
            post(url, auth, 'contests', f'yaml=@{base}/contest.yaml')
            post(url, auth, f'contests/toy-{lang}/problems/add-data', f'data=@{base}/problems.yaml')
            for p in PROBLEMS:
                pid = f"{p['id']}-{lang}"
                post(url, auth, f'contests/toy-{lang}/problems', f'zip=@{base}/{pid}.zip', f'problem={pid}')
    elif len(sys.argv) != 1:
        sys.exit(__doc__)


if __name__ == '__main__':
    main()
