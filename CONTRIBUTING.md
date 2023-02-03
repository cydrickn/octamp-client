# Contributing

As a contributor, here are the guidelines we would like you to follow:

- [Branch Naming](#branch)
- [Submitting a Pull Request](#pr)
- [Code Rules](#rules)
- [Commit Message Guidelines](#commit)

## <a name="branch"></a> Branch Naming

Naming your branch should follow this guidelines:

```shell
<group>-<issue-id>-<short-description>
```

1. Start branch name with a Group word
2. Add issue ID (optional if there is single issue related)
3. Use hyphen as separator
4. Avoid long descriptive names for long-lived branches
5. Lower case

**Example**
```shell
bug-1-fix-game
feature-2-create-page
docs-update-contributing
```

### Group Word

- **bug**: the branch is for fixing a bugs that is related to core code, logic, pages or any thats affect the codes.
- **feature**: the branch is for adding new feature.
- **enhancement**: the branch is for enhancement.
- **hotfix**: the branch is for fixing a hotfix error.
- **ci**: the branch is related to ci/cd, this includes a bug that only occurs in ci / cd without changes in core codes.
- **docs**: the branch is related to adding or updating a documents.
- **test**: the branch is related only in adding or updating test, but if the test is added / updated with a core code,
  we should not use this.

### Branching Out

We recommend you to branch from the main branch for now.

```shell
git checkout -b bug-1-fix-game origin/main
```

### Updating Branch
Always rebase your branch from main
```shell
git rebase origin/main
```
or merge is fine also
```shell
git merge origin/main
```

### Using same branch

We will have a problem when your using same branch all the time.

When you already merge your branch to main via pull request. This means the history of main branch is already changed.

We are doing squashing when we are merged the branches, so means this will put all your changes to one commit,
in this case all the history of commits in your local machine that you used to push to the branch will be invalidated.

So we recommend you to instead using same branch, just create a new branch with different name since you are doing feature or bugfix.

**How about you need to fix a something in the code that was recently merge? Can you use your old branch name?**
You will still not use your old branch name as we recommend, but you can just delete and create that branch.
But as we recommend we should not, reason for that is we can specify if that the fix that you will fix will be a hotfix or
bugfix, so you can just create a new branch like. `bug-fix-game` `hotfix-user-creation`


## <a name="pr"></a> Submitting a Pull Request

Before you submit your Pull Request (PR) consider the following guidelines:

1. Search for an open or closed PR
   that relates to your submission. You don't want to duplicate effort.
1. Pull the repository.
1. Make your changes in a new git branch, you should follow the correct format of the branch name [Branch Naming](#branch):
     ```shell
     git checkout -b bug-1-fix-game origin/main
     ```

1. Create your patch, **including appropriate test cases, if possible**.
1. Follow our [Coding Rules](#rules).
1. Commit your changes using a descriptive commit message that follows our
   [commit message conventions](#commit). Adherence to these conventions
   is necessary because release notes are automatically generated from these messages.

     ```shell
     git commit -a
     ```
   Note: the optional commit `-a` command line option will automatically "add" and "rm" edited files.

1. Push your branch to GitHub:

    ```shell
    git push origin bug-1-fix-game
    ```

1. In Gitlab, send a pull request to `main`.
* If we suggest changes then:
    * Make the required updates.
    * Rebase your branch and force push to your branch (this will update your Pull Request):

      ```shell
      git rebase main -i
      git push -f origin my-fix-branch
      ```

#### After your pull request is merged

After your pull request is merged, you can safely delete your branch and pull the changes
from the main (upstream) repository:

* Check out the main branch:

    ```shell
    git checkout main -f
    ```

* Delete the local branch:

    ```shell
    git branch -D my-fix-branch
    ```

* Update your main with the latest upstream version:

    ```shell
    git pull --ff upstream main
    ```

## <a name="rules"></a> Code Rules (TODO)

## <a name="commit"></a> Commit Message Guidelines

We want to make the commit message to have standard and this will also help some automation for
changelog to properly parse the correct data.

Commit messages should follow conventional commit.

This conventional should be followed in each commit and Including merge request / merge commit

### Commit Message Format

```
<type>(scope): <description>
<body>
<footer>
```

The **type** is a mandatory while the **scope** is optional

The **description** is also mandatory since here you will put the summary of the commit

The **body** and **footer** are optional

Any line of the commit message cannot be longer 100 characters! This allows the message to be easier to read on GitHub
as well as in various git tools.

Examples:

```
docs(contributing): create contributing rules
```

```
fix(page): fix signup page
Fix the the errors in signup page
```

```
fix(api): Fix the errors occurs in signup page
Fix the the api for signup page
BREAKING CHANGE: removing the id field
```

### Revert

If the commit reverts a previous commit, it should begin with `revert:` , followed by the header of the reverted commit.
In the body it should say: `This reverts commit <hash>.`, where the hash is the SHA of the commit being reverted.

### Types

- **build**: Changes that affect the build system like gulp, npm, etc
- **ci**: Changes made to the CI configuration like Travis, Circle, Actions
- **docs**: Documentation only changes
- **chore**: Changes that are not part of assets, components, layouts, middleware, pages, images and fonts. Most likely
  this changes will be on configurations and dependencies
- **feat**: A new feature
- **fix**: Fixed a bug
- **perf**: Code changes that improve performance
- **refactor**: A code change that's not particularly a bug or new feature
- **revert**: Revert a previous commit
- **style**: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc)
- **test**: Add or fix tests
- **merge**: Use for merge commit, only use if you are merging multiple commit

### Scope

- **page**: Changes in any pages
- **assets**: Changes in scss, fonts and images
- **components**: Changes in any components
- **layouts**: Changes in any layouts
- **middleware**: Changes in any middleware
- **changelog**: used for updating the release notes in CHANGELOG.md
- **contribute**: used for updating CONTRIBUTING.md
- **readme**: used for updating README.md

### Subject

The subject contains a succinct description of the change:

- use the imperative, present tense: "change" not "changed" nor "changes"
- don't capitalize the first letter
- no dot (.) at the end

### Body

Just as in the subject, use the imperative, present tense: "change" not "changed" nor "changes". The body should include the motivation for the change and contrast this with previous behavior.

### Footer
The footer should contain any information about **Breaking Changes** and is also the place to reference GitHub issues that this commit **Closes**.

**Breaking Changes** should start with the word `BREAKING CHANGE:` with a space or two newlines. The rest of the commit message is then used for this.

A detailed explanation can be found in this document.

### Merge Commit Message

The merge commit follow same format with the commit message.
But for the subject we will add the merge request number.

And for type, we should put `merge` if the request has multiple commits, but if only one or most of the commit only persist
to one type, we should use the correct type.

The footer should contain a [closing reference to an issue](https://docs.gitlab.com/ee/user/project/issues/managing_issues.html#default-closing-pattern) if any.

Examples:

```
fix(page): fix signup page
Fix the the errors in signup page

Closes #1
```
