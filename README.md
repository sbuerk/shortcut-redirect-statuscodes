# TYPO3 Extension `shortcut_redirect_statuscodes`

This extension enhance the `Shortcut Redirect` capability by giving the editor the choice of
the `HTTP Redirect Status` code for a shortcut redirect. To achieve this, the corresponding
backend form is extended with a status code select box and the TYPO3 core PSR-15 middleware
dealing with `Shortcut Redirects` is replaced to respect the selected status code.

|                  | URL                                                     |
|------------------|---------------------------------------------------------|
| **Repository:**  | https://github.com/sbuerk/shortcut-redirect-statuscodes |
| **Read online:** | -                                                       |
| **TER:**         | -                                                       |

## Compatibility

| shortcut_redirect_statuscodes | TYPO3   | PHP       | Support / Development       |
|-------------------------------|---------|-----------|-----------------------------|
| dev-main                      | 11 - 12 | 7.4 - 8.3 | unstable development branch |


