#!/usr/bin/python3

import re
import pygit2
import sys
import bleach
import markdown

import aurweb.config
import aurweb.db

repo_path = aurweb.config.get('serve', 'repo-path')
commit_uri = aurweb.config.get('options', 'commit_uri')


class LinkifyPreprocessor(markdown.preprocessors.Preprocessor):
    _urlre = re.compile(r'(\b(?:https?|ftp):\/\/[\w\/\#~:.?+=&%@!\-;,]+?'
                        r'(?=[.:?\-;,]*(?:[^\w\/\#~:.?+=&%@!\-;,]|$)))')

    def run(self, lines):
        return [self._urlre.sub(r'<\1>', line) for line in lines]


class LinkifyExtension(markdown.extensions.Extension):
    def extendMarkdown(self, md, md_globals):
        md.preprocessors.add('linkify', LinkifyPreprocessor(md), '_end')


class GitCommitsPreprocessor(markdown.preprocessors.Preprocessor):
    _oidre = re.compile(r'(\b)([0-9a-f]{7,40})(\b)')
    _repo = pygit2.Repository(repo_path)
    _head = None

    def __init__(self, md, head):
        self._head = head
        super(markdown.preprocessors.Preprocessor, self).__init__(md)

    def handleMatch(self, m):
        oid = m.group(2)
        if oid not in self._repo:
            return oid

        prefixlen = 12
        while prefixlen < 40:
            if oid[:prefixlen] in self._repo:
                break
            prefixlen += 1

        html = '[`' + oid[:prefixlen] + '`]'
        html += '(' + commit_uri % (self._head, oid[:prefixlen]) + ')'

        return html

    def run(self, lines):
        return [self._oidre.sub(self.handleMatch, line) for line in lines]


class GitCommitsExtension(markdown.extensions.Extension):
    _head = None

    def __init__(self, head):
        self._head = head
        super(markdown.extensions.Extension, self).__init__()

    def extendMarkdown(self, md, md_globals):
        preprocessor = GitCommitsPreprocessor(md, self._head)
        md.preprocessors.add('git-commits', preprocessor, '_end')


def get_comment(conn, commentid):
    cur = conn.execute('SELECT PackageComments.Comments, PackageBases.Name '
                       'FROM PackageComments INNER JOIN PackageBases '
                       'ON PackageBases.ID = PackageComments.PackageBaseID '
                       'WHERE PackageComments.ID = ?', [commentid])
    return cur.fetchone()


def save_rendered_comment(conn, commentid, html):
    conn.execute('UPDATE PackageComments SET RenderedComment = ? WHERE ID = ?',
                 [html, commentid])


def main():
    commentid = int(sys.argv[1])

    conn = aurweb.db.Connection()

    text, pkgbase = get_comment(conn, commentid)
    html = markdown.markdown(text, extensions=['nl2br', LinkifyExtension(),
                                               GitCommitsExtension(pkgbase)])
    allowed_tags = bleach.sanitizer.ALLOWED_TAGS + ['p', 'br']
    html = bleach.clean(html, tags=allowed_tags)
    save_rendered_comment(conn, commentid, html)

    conn.commit()
    conn.close()


if __name__ == '__main__':
    main()
