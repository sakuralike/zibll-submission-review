# 子比前台投稿审核插件 · 产品规格文档（PRD）

| 项目 | 内容 |
| --- | --- |
| 产品名称 | 子比前台投稿审核插件（Zibll Frontend Submission Review） |
| 插件标识 | `zibll-submission-review` |
| 目标主题 | Zibll 子比主题（`Text Domain: zib_language`），验证版本 **V9.0** |
| 文档版本 | v1.0 |
| 文档状态 | 待评审 |
| 编写日期 | 2026-10-05 |

> **代码事实来源声明**：本文档中所有关于主题目录结构、文件路径、函数名、钩子名、CSS 类名的描述，均基于本地 `E:\只比主题\zibll` 主题源码实测（V9.0，`style.css` 第10 行 `Version: 9.0`），并标注了 `文件:行号`。凡主题中确实不存在的实现，一律显式标注「主题未提供」，不做臆造。

---

## 目录

1. [产品背景与目标](#1-产品背景与目标)
2. [范围定义（In Scope / Non-goals）](#2-范围定义in-scope--non-goals)
3. [术语表](#3-术语表)
4. [用户角色与权限矩阵](#4-用户角色与权限矩阵)
5. [后台设置项清单](#5-后台设置项清单)
6. [前端交互流程与页面结构](#6-前端交互流程与页面结构)
7. [数据存储方案](#7-数据存储方案)
8. [与 Zibll 主题的集成点与兼容性要求](#8-与-zibll-主题的集成点与兼容性要求)
9. [样式规范](#9-样式规范)
10. [安全与性能要求](#10-安全与性能要求)
11. [边界情况与验收标准](#11-边界情况与验收标准)
12. [附录](#12-附录)

---

## 1. 产品背景与目标

### 1.1 现状事实

Zibll 主题**已经具备**前台投稿能力与后台审核机制，但审核动作**只能在 WordPress 后台完成**。实测代码事实：

- 前台投稿页模板存在：`pages/newposts.php`，模板名为 `Zibll-写文章、投稿页面`（`pages/newposts.php:4-5`）。
- 投稿保存入口：`action/new_posts.php` → `zib_ajax_new_posts()`，通过 `wp_ajax_posts_save` / `wp_ajax_posts_draft` 注册（`action/new_posts.php:284-287`）。
- 审核状态判定：投稿时若用户无免审权限，文章被置为 `post_status = 'pending'`（`action/new_posts.php:205`）；有免审权限则直接 `publish`（`action/new_posts.php:208`）。
- 插入前拦截钩子：`do_action('zib_pre_insert_post', $postarr)`（`action/new_posts.php:218`）。
- 提交后通知：`do_action('new_posts_pending', $new_post_obj)`（`action/new_posts.php:261`），该钩子已被主题用于向管理员发送邮件（`inc/functions/message/functions/new.php:164`）。
- 审核权限能力键：`new_post_add`（发布新文章）、`new_post_audit_no`（免审直发）、`new_post_audit_no_manual`（免人工审核）、`new_post_edit`、`new_post_delete`，定义于 `inc/options/options-module.php:1478-1570`。
- **主题未提供任何前台审核界面**：全主题检索 `wp_ajax_.*audit` 仅命中论坛版块/帖子的审核（`inc/functions/bbs/action/ajax-posts.php:544-545`）与评论审核（`action/comment.php:68`），普通文章（`post`）的投稿审核**没有对应的前端入口**。

### 1.2 用户问题

| 编号 | 问题 | 影响的用户 |
| --- | --- | --- |
| P1 | 投稿作者无法看到自己的稿件处于「待审核 / 已驳回」，只能靠猜或反复询问管理员 | 投稿作者 |
| P2 | 审核人必须进入 WP 后台逐条翻找 `pending` 文章，且看不到前台渲染效果，容易误判排版/图片问题 | 审核人（版主、社区运营） |
| P3 | 驳回时无法在同一界面留下结构化意见，作者拿不到可追溯的修改建议 | 投稿作者 + 审核人 |
| P4 | 部分小工具（如私密内容、下载模块、用户卡片）只应对登录会员可见，但 WordPress小工具机制没有「按访客登录态」这一层开关 | 站点运营 |

### 1.3 产品目标

| 编号 | 目标 | 对应问题 | 度量方式 |
| --- | --- | --- | --- |
| G1 | 在主题前端提供**独立页面**（非短代码），完成「提交稿件 + 浏览我的审核状态 + 审核他人稿件」全链路 | P1 P2 P3 | 功能验收用例 AC-01 ~ AC-12 |
| G2 | 后台可指定**哪些用户组**具备前台审核权限，审核动作与状态流转规则可配置 | P2 P3 | 功能验收用例 AC-13 ~ AC-18 |
| G3 | 自动枚举当前主题**已启用**的小工具，支持逐个配置「登录后可见」，配置独立存储并在注册/渲染环节生效 | P4 | 功能验收用例 AC-19 ~ AC-24 |
| G4 | 100% 复用主题现有样式体系与前端框架，不引入任何独立 CSS/JS 框架 | — | 验收标准 §11.4 |
| G5 | 对主题零侵入：主题文件零修改，主题更新后功能不失效 | — | 验收标准 §11.5 |

### 1.4 成功指标

| 指标 | 定义 | 基线 | 目标 |
| --- | --- | --- | --- |
| 北极星指标 | 前台完成的稿件审核占比（前台审核数 / 全部审核数） | 0%（主题无此能力） | ≥ 60% |
| 驱动指标 1 | 审核平均处理时长（提交 → 状态变更） | 需上线后测量 | 较基线下降 30% |
| 驱动指标 2 | 投稿状态自助查询占比（作者查看状态 / 提交次数） | 0% | ≥ 50% |
| 健康指标 | 审核动作 AJAX 失败率 | — | < 0.5% |
| 健康指标 | 插件引入的前台额外 TTFB | — | < 30ms |
| 健康指标 | 因插件导致的 PHP 报错/告警 | — | 0 |

---

## 2. 范围定义（In Scope / Non-goals）

### 2.1 In Scope

**模块 A · 投稿审核**

- A1. 通过**页面模板（Page Template）**新增一个前台独立页面，不使用短代码（Shortcode）。
- A2. 页面提供三个视图：`我的投稿`（列表 + 提交/编辑表单）、`审核台`（待审队列 + 详情 + 审核操作）、`审核记录`（本机审核历史）。
- A3. 页面 URL 由插件自动创建/复用 WP 页面（复用主题 `zib_get_template_page_url()` 的模式），无需管理员手工建页。
- A4. 投稿复用主题既有投稿链路（`new_post_add` 能力键、`wp_ajax_posts_save` 范式、`zib_pre_insert_post` 拦截），不重写编辑器与上传。
- A5. 审核动作：通过（approve）/ 驳回（reject）/ 退回（return）三态，状态机可配置。
- A6. 后台可配置：谁有前台审核权限、审核动作是否启用、退回/驳回是否强制填写意见、审核通知开关、状态文案。
- A7. 审核结果通过主题站内信与邮件双通道通知作者。

**模块 B · 小工具控制**

- B1. 枚举当前站点**已注册**与**已启用**（已放置到某侧边栏）的全部小工具。
- B2. 后台以列表形式呈现，逐个小工具提供「仅登录后可见」开关。
- B3. 配置项独立存储（不写入 `zibll_options`）。
- B4. 渲染环节生效：未登录访客访问被锁定的小工具时，输出登录引导占位（或整块隐藏，取决于配置）。
- B5. 支持按小工具类型（id_base）批量筛选、搜索、批量开关。

### 2.2 Non-goals（明确不做，防止范围蔓延）

| 编号 | 不做的事 | 理由 |
| --- | --- | --- |
| N1 | 不替换、不重写主题的前台投稿编辑器与媒体上传 | 复用 `pages/newposts.php` 既有能力，避免与主题 TinyMCE 过滤器（`tinymce_upload_img` 等）冲突 |
| N2 | 不做定时/延时审核、定时发布 | 主题已有定时任务体系，不重复建设 |
| N3 | 不做 AI 内容审核 | 主题已有 API 审核（`_pz('audit_new_post')` + `ZibAudit::is_audit()`，见 `action/new_posts.php:154-158`），插件不重复实现 |
| N4 | 不做多级审核链（初审/复审/终审） | v1 为单级审核，多级留作 v2 扩展字段 |
| N5 | 不做邮件模板自定义 UI，仅提供开关与文案变量 | 复用主题 `zib_newmsg_publish_to_pending()`（`inc/functions/message/functions/new.php:317`） |
| N6 | 不支持小工具**实例级**（同一 id_base 的第 1 个与第 3 个实例分别设置）权限，只做 **id_base 级** | 需求为「逐个小工具」，id_base 是用户认知中的「小工具」；实例级会让配置项数量爆炸且与 `widget_*` option 的实例键耦合 |
| N7 | 不修改主题任何文件（禁止改 `zibll` 主题源码或 `func.php`） | 保证主题可正常升级 |
| N8 | 不引入 Vue/React/Tailwind/Bootstrap 等独立前端框架 | 需求明确；主题已内置 Bootstrap 3 基础样式与 jQuery |
| N9 | 不做前台审核的批量操作（勾选 20 条一次性通过） | v1 聚焦单条审核的正确性与可追溯性 |
| N10 | 不做投稿的定时撤回、协作文编辑 | 超出审核范畴 |

### 2.3 与既有功能的边界

| 能力 | 归属 | 说明 |
| --- | --- | --- |
| 投稿内容编辑器的富文本能力 | 主题 | `pages/newposts.php` 及其 `tinymce_*` 过滤器 |
| 投稿提交到 `pending` 的判定 | 主题 | `action/new_posts.php:205`，插件不改动该判定，仅在审核侧扩展 |
| 免审直发策略 | 主题 | `new_post_audit_no` / `new_post_audit_no_manual` |
| 审核动作的**执行** | 插件 | 前台执行，调用 `wp_update_post()` 改 `post_status` |
| 审核站内信/邮件文案模板 | 主题 | 复用 `zib_newmsg_publish_to_pending()` |

---

## 3. 术语表

| 术语 | 定义 |
| --- | --- |
| Zibll / 子比主题 | 本插件的目标主题，代码根目录 `E:\只比主题\zibll` |
| CSF | Codestar Framework，主题使用的设置框架（`inc/codestar-framework/`，v2.2.0）；主题通过 `csf_override` 过滤器用 `inc/csf-framework/` 覆盖了部分内核类 |
| id_base | WordPress 小工具的类型标识，如 `zib_widget_ui_main_post`。小工具实例 ID 形如 `{id_base}-{数字}` |
| 「已注册」小工具 | 存在于 `$wp_widget_factory->widgets` 中的小工具对象 |
| 「已启用」小工具 | 已出现在 `wp_get_sidebars_widgets()` 某个侧边栏数组中的小工具实例 |
| CSF 轨/ 旧轨 | 主题小工具的两套实现：CSF 轨（`CSF_Widget` 子类，42 个）与旧轨（直接 `extends WP_Widget`，9 个） |
| 能力键（capability） | Zibll 的权限标识，如 `new_post_add`。存储于 `zibll_options['user_cap']` |
| 身份（role） | Zibll 的 8 类用户身份：all / logged / level / vip / auth / moderator / plate_author / cat_moderator |
| 免审直发 | 用户拥有 `new_post_audit_no` 能力时，投稿直接 `publish`，不进入待审队列 |

---

## 4. 用户角色与权限矩阵

### 4.1 Zibll 身份体系（插件必须复用，不可自建角色）

**关键事实：Zibll 主题没有注册任何自定义 WordPress 角色**。全主题检索 `add_role` / `add_cap` 为 0 处（仅 `inc/codestar-framework/classes/fields.class.php:226-234` 读取 `$wp_roles`）。主题的全部权限建立在「8 类身份 × 能力键」的矩阵上，存储于 `zibll_options['user_cap']`。

判定入口（`inc/functions/user/user-cap.php:21-214`）：

```php
// inc/functions/user/user-cap.php:21
function zib_user_can($user_id, $capability, ...$args)
{
    $is_can = false;
    if (is_super_admin($user_id)) {   // 超级管理员直接放行
        $is_can = true;
    }
    if (!$is_can) {
        $cap_roles = zib_get_cap_roles($capability);
        switch ($capability) {
            case 'new_post_delete':
            case 'new_post_edit':
                // 稿件类能力需校验「是否本人」
                if (!empty($args[0])) {
                    $post = $args[0];
                    if (isset($post->post_author) && $post->post_author == $user_id) {
                        if ('draft' === $post->post_status) {
                            $is_can = true;  // 自己的草稿可直接编辑/删除
                        } else {
                            $is_can = zib_is_can_roles($user_id, $cap_roles);
                        }
                    }
                }
                break;
            default:
                $is_can = zib_is_can_roles($user_id, $cap_roles);
                break;
        }
    }
    return apply_filters('zib_user_can', $is_can, $user_id, $capability, $args); // :213
}

// :227
function zib_current_user_can($capability, ...$args)
{
    return zib_user_can(get_current_user_id(), $capability, ...$args);
}
```

`zib_is_can_roles()` 的匹配顺序（`inc/functions/user/user-cap.php:233-267`）：`all` → `logged` → `vip` → `level` → `auth` → 过滤器 `is_can_roles`。**注意：主题并未在默认流程中匹配 `moderator` / `plate_author` / `cat_moderator` 三类社区身份**，这三类仅在后台权限矩阵 UI 中可选，默认需通过 `is_can_roles` 过滤器自行接入。

**插件扩展点**：`apply_filters('is_can_roles', false, $user_id, $cap_roles)`（`:263`）—— 插件需要让「版主」等社区身份获得审核权时，挂这个过滤器，**不改主题代码**。

### 4.2 8 类身份定义

来源：`inc/options/options-module.php:1356-1438`，汇总数组 `$roles_all`（`:1438`）：

| key | 中文名 | 控件类型 | 定义行号 | 判定依据 |
| --- | --- | --- | --- | --- |
| `all` | 所有人（含游客） | switcher | `:1359-1366` | 直接 `true` |
| `logged` | 已登录用户 | switcher | `:1367-1374` | `get_current_user_id()` 非空 |
| `level` | 用户等级 ≥ N | spinner | `:1378-1388` | `zib_get_user_level()`，上限 `_pz('user_level_max', 10)` |
| `vip` | 会员等级 ≥ N | spinner | `:1391-1401` | `zib_get_user_vip_level()`，定义于 `zibpay/functions/zibpay-vip.php:853` |
| `auth` | 认证用户 | switcher | `:1403-1409` | `zib_is_user_auth()`（`inc/functions/user/user-auth.php:15`） |
| `moderator` | 版主 | switcher | `:1410-1416` | `zib_bbs_is_the_moderator()` |
| `plate_author` | 超级版主（版块作者） | switcher | `:1417-1424` | 同上，`=== 'plate_author'` |
| `cat_moderator` | 分区版主 | switcher | `:1425-1431` | `zib_bbs_is_the_cat_moderator()` |

`level` 与 `vip` 字段是**条件显示**的（`inc/options/options-module.php:1376-1402`）：仅当 `_pz('user_level_s', true)` / `_pz('pay_user_vip_1_s', true)` 开启时才渲染。

### 4.3 插件新增能力键

插件在激活时向 `zibll_options['user_cap']` 追加以下键（**不覆盖已有键**），并通过 CSF 设置页暴露 UI：

| 能力键 | 名称 | 用途 | 默认身份配置 |
| --- | --- | --- | --- |
| `zsr_submit` | 前台提交稿件 | 进入投稿页、提交/编辑自己的稿件 | `logged => true` |
| `zsr_review` | 前台审核稿件 | 可访问「审核台」，可执行审核动作 | `moderator => true`, `plate_author => true`, `cat_moderator => true` |
| `zsr_review_others` | 审核他人稿件 | 可审核非本人提交的稿件（`zsr_review` 的子集，用于「只审自己的站点」） | 继承 `zsr_review` |
| `zsr_manage` | 管理插件设置 | 可访问插件设置页（后台） | `all => false`（默认仅管理员，由 `is_super_admin()` 兜底） |

**注册实现要点**（`inc/capabilities.php`）：

```php
add_action('admin_init', 'zsr_register_capabilities', 5);
function zsr_register_capabilities()
{
    $caps = _pz('user_cap');                       // inc/dependent.php:195
    if (!is_array($caps)) { $caps = array(); }
    // 幂等追加，绝不覆盖站点已配置的同名键
    if (!isset($caps['zsr_submit']))       { $caps['zsr_submit']       = array('logged' => true); }
    if (!isset($caps['zsr_review']))       { $caps['zsr_review']       = array('moderator' => true, 'plate_author' => true, 'cat_moderator' => true); }
    if (!isset($caps['zsr_review_others'])){ $caps['zsr_review_others']= $caps['zsr_review']; }
    if (!isset($caps['zsr_manage']))       { $caps['zsr_manage']       = array(); }
    _spz('user_cap', $caps);                      // inc/dependent.php:216
}
```

> **注意**：主题的权限设置 UI 由 `CFS_Module::user_can_fields()`（`inc/options/options-module.php:1677-1750`）在渲染时遍历 `user_caps()` 的**静态定义**生成。插件新增的键不会自动出现在主题设置页，这是**符合预期的**——插件能力键由插件自己的设置页管理，避免污染主题设置界面。插件在判定时统一走 `zib_current_user_can('zsr_review')`，从而自动享受 `is_super_admin()` 兜底与 `zib_user_can` 过滤器。

### 4.4 权限矩阵

**说明**：「✔」= 允许，「—」= 不允许，「C」= 由后台配置决定。

| 角色 / 身份 | 访问投稿页 | 提交稿件 | 查看自己的稿件与状态 | 编辑自己的稿件 | 访问审核台 | 审核他人稿件 | 配置小工具可见性 | 插件设置 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 游客（未登录） | — | — | — | — | — | — | — | — |
| 已登录普通用户（`logged`） | ✔ | C（`zsr_submit`） | ✔（仅本人） | ✔（仅本人 `draft`/`pending`） | — | — | — | — |
| 认证用户（`auth`） | ✔ | C | ✔ | ✔ | — | — | — | — |
| 会员 VIP（`vip`） | ✔ | C | ✔ | ✔ | — | — | — | — |
| 版主（`moderator`） | ✔ | C | ✔ | ✔ | C（`zsr_review`） | C（`zsr_review_others`） | — | — |
| 分区版主（`cat_moderator`） | ✔ | C | ✔ | ✔ | C | C | — | — |
| 超级版主（`plate_author`） | ✔ | C | ✔ | ✔ | C | C | — | — |
| 主题管理员（`is_super_admin()`） | ✔ | ✔（兜底） | ✔ | ✔ | ✔（兜底） | ✔（兜底） | ✔ | ✔ |
| 小工具被锁定时的访客 | 看到登录引导占位 | — | — | — | — | — | — | — |

### 4.5 数据可见性规则

| 场景 | 可见范围 | 实现 |
| --- | --- | --- |
| 「我的投稿」列表 | `post_author = 当前用户` 且 `post_status IN ('draft','pending','publish','trash')` 且 `post_type = 'post'` | `WP_Query` 加 `author` 参数 |
| 「审核台」列表 | `post_status = 'pending'` 且 `post_type = 'post'`，且（`zsr_review_others` 为真 **或** `post_author = 当前用户`） | `WP_Query` + `post__not_in` 排除无权稿件 |
| 「审核记录」列表 | `post_meta` 中 `zsr_reviewed_by = 当前用户` | `WP_Query` + `meta_query` |
| 小工具可见性 | 未锁定 → 所有人；已锁定 → 仅 `is_user_logged_in()` 为真 | 见 §8.5 |

**越权防护**：所有列表查询在`pre_get_posts` 阶段注入条件，不依赖前端过滤；`post_author != 当前用户` 且无 `zsr_review_others` 时，详情页一律 403（`wp_safe_redirect` + 主题 404 模板 `template/content-404`）。

---

## 5. 后台设置项清单

### 5.1 设置框架接入方式

**事实**：主题通过 CSF 创建后台主设置页，option键为 `zibll_options`（`inc/options/admin-options.php:16-42`）：

```php
// inc/options/admin-options.php:19-38
$prefix = 'zibll_options';
CSF::createOptions($prefix, array(
    'menu_title'      => __('zibll主题设置', 'zib_language'),
    'menu_slug'       => 'zibll_options',
    'framework_title' => __('子比主题', 'zib_language'),
    'show_in_customizer' => false,
    'save_defaults'   => !$no_create,   // 首次安装自动落盘默认值
    'theme'           => 'light',
));
```

**插件决策：独立顶级菜单，不注入主题设置页。**

| 方案 | 评估 | 结论 |
| --- | --- | --- |
| A.通过 `csf_zibll_options_sections` 过滤器往主题设置页塞section（过滤器存在于 `inc/csf-framework/classes/admin-options.class.php:98`） | 与主题 UI 融合，但主题升级可能移除过滤器；且插件卸载后残留空 section | ❌ |
| B. 用 `CSF::createOptions('zsr_options', ...)` 建独立顶级菜单 | 干净隔离，卸载即清| ✅ **采用** |
| C. 用 WP 原生 `register_setting()` | 与主题视觉体系不一致 | ❌ |

**实现**：

```php
// inc/admin/options.php
add_action('after_setup_theme', 'zsr_admin_options', 20);
function zsr_admin_options()
{
    if (!is_admin()) { return; }
    CSF::createOptions('zsr_options', array(
        'menu_title'      => __('投稿审核设置', 'zib-sub-review'),
        'menu_slug'       => 'zsr_options',
        'framework_title' => __('子比前台投稿审核插件', 'zib-sub-review'),
        'theme'           => 'light',
        'save_defaults'   => true,
        'footer_text'     => __('复用 Zibll 主题样式与权限体系', 'zib-sub-review'),
    ));
    // 5 个 section 见下文
}
```

> `after_setup_theme` + 优先级 20：确保晚于主题的 `zib_csf_admin_options`（`inc/options/admin-options.php:12725`，默认优先级 10），避免菜单顺序冲突。

### 5.2 设置项总表

> 所有字段 ID **无统一前缀是主题惯例**（实测 944 个字段 ID 中仅 1 个以 `zib_` 开头，命名风格为 `{模块}_{语义}`）。插件采用 `zsr_` 前缀以避免与主题字段冲突。
> 字段类型取自实测 CSF 类型分布（`switcher` 353 个、`text` 191 个、`textarea` 86 个、`select` 58 个、`spinner` 46 个等）。

#### Section 1 · `zsr_general` 基础设置

| 字段 ID | 字段名 | 类型 | 默认值 | 作用 |
| --- | --- | --- | --- | --- |
| `zsr_enable` | 启用投稿审核功能 | `switcher` | `true` | 关闭后：前台审核页返回 404，小工具控制不再生效（已锁定小工具恢复默认展示） |
| `zsr_enable_submit` | 启用前台投稿表单 | `switcher` | `true` | 关闭后审核页仅保留「我的投稿」与「审核台」，投稿入口跳转至主题原生投稿页 `zib_get_template_page_url('pages/newposts.php')` |
| `zsr_enable_review` | 启用前台审核台 | `switcher` | `true` | 关闭后不注册审核视图与审核 AJAX；`zsr_review` 能力键失效 |
| `zsr_page_id` | 前台页面 ID | `number` | `0`（自动） | 插件自动创建的前台页面 ID，0 表示尚未创建/需重建 |
| `zsr_page_slug` | 前台页面别名 | `text` | `submissions` | 自动创建页面时使用的 `post_name`；已存在页面时不生效 |
| `zsr_menu_label` | 顶部菜单名称 | `text` | `我的投稿` | 插件在主题菜单注册的前台入口文案；留空则不注册菜单 |
| `zsr_show_menu_item` | 显示前台菜单入口 | `switcher` | `true` | 关闭后仅可通过直链访问页面 |
| `zsr_menu_position` | 菜单位置 | `select` | `0` | 复用主题菜单位置；选项来源取自主题注册的导航位置，未匹配时降级为 `0` |

#### Section 2 · `zsr_roles` 权限设置

> 全部为 `fieldset`，内嵌与主题一致的 8 类身份控件（复用 `CFS_Module::user_can_user_fields()` 的结构，字段级默认值单独落盘）。

| 字段 ID | 字段名 | 类型 | 默认值 | 作用 |
| --- | --- | --- | --- | --- |
| `zsr_cap_submit` | 提交稿件权限 | `fieldset` | `array('logged' => true)` | 写入 `zibll_options['user_cap']['zsr_submit']`。允许进入投稿视图并提交 |
| `zsr_cap_review` | 前台审核权限 | `fieldset` | `array('moderator' => true, 'plate_author' => true, 'cat_moderator' => true)` | 写入 `zibll_options['user_cap']['zsr_review']`。允许访问审核台 |
| `zsr_cap_review_others` | 可审核他人稿件 | `fieldset` | 同 `zsr_cap_review` | 写入 `zibll_options['user_cap']['zsr_review_others']`。关闭后审核人只能审核自己提交的稿件 |
| `zsr_review_self_only` | 仅审核本人稿件 | `switcher` | `false` | 快捷开关。开启时强制 `zsr_cap_review_others` 为空，等价于审核人只能审核自己的稿件 |

#### Section 3 · `zsr_actions` 审核动作与状态流转

| 字段 ID | 字段名 | 类型 | 默认值 | 作用 |
| --- | --- | --- | --- | --- |
| `zsr_actions` | 启用的审核动作 | `checkbox` | `array('approve', 'reject', 'return')` | 可选 `approve`（通过）/ `reject`（驳回）/ `return`（退回修改）。至少保留 1 项，保存时校验 |
| `zsr_approve_to_status` | 通过后置为状态 | `select` | `publish` | 选项：`publish`（直接发布）/ `pending`（仅标记为已通过，仍由 WP 后台发布）。为 `publish` 时插件执行 `wp_update_post()` |
| `zsr_approve_keep_audit` | 通过后是否二次审核 | `switcher` | `false` | 开启时通过后仍置 `pending`，但写入 `zsr_state=approved`，实现「复核」语义（v1 用于流程占位） |
| `zsr_reject_to_status` | 驳回后置为状态 | `select` | `pending` | 选项：`pending`（留在待审队列，作者可修改重提）/ `draft`（退回作者草稿箱）/ `trash`（移入回收站） |
| `zsr_return_to_status` | 退回后置为状态 | `select` | `draft` | 选项：`draft`（作者可继续编辑）/ `pending`（重回待审队列） |
| `zsr_reject_reason_required` | 驳回必须填写原因 | `switcher` | `true` | 开启时驳回表单未填原因直接返回错误 |
| `zsr_return_reason_required` | 退回应填写意见 | `switcher` | `false` | 关闭时允许空意见退回 |
| `zsr_reason_maxlength` | 意见字数上限 | `spinner` | `200` | 前端 `maxlength` 与后端 `mb_substr` 双重限制 |
| `zsr_allow_self_review` | 允许审核自己的稿件 | `switcher` | `false` | 关闭时若 `post_author == 当前用户` 则隐藏审核操作区 |
| `zsr_allow_re_review` | 允许重复审核 | `switcher` | `true` | 关闭后已审核过的稿件对同一审核人不再显示操作按钮 |
| `zsr_re_review_window` | 重复审核冷却（小时） | `number` | `0`（不限制） | `zsr_allow_re_review=false` 时生效；同一审核人处理过该稿件后在窗口期内不可再审 |

#### Section 4 · `zsr_notify` 通知设置

| 字段 ID | 字段名 | 类型 | 默认值 | 作用 |
| --- | --- | --- | --- | --- |
| `zsr_notify_author` | 审核结果通知作者 | `switcher` | `true` | 走主题站内信 + 邮件双通道 |
| `zsr_notify_channel` | 通知通道 | `checkbox` | `array('msg', 'email')` | `msg` = 主题站内信（`inc/functions/message/functions/new.php`），`email` = `wp_mail` |
| `zsr_notify_approver` | 新稿件提醒审核人 | `switcher` | `false` | 复用主题已有的 `new_posts_pending` 钩子（`action/new_posts.php:261`），插件仅追加提醒，不重复实现 |
| `zsr_notify_on_return` | 退回时通知作者 | `switcher` | `true` | 关闭后退回操作不发通知 |
| `zsr_notify_include_content` | 通知中包含内容摘要 | `switcher` | `true` | 关闭后通知仅含标题与链接。摘要长度复用 `zib_str_cut(..., 200)` |

#### Section 5 · `zsr_widget` 小工具控制

| 字段 ID | 字段名 | 类型 | 默认值 | 作用 |
| --- | --- | --- | --- | --- |
| `zsr_widget_enable` | 启用小工具登录可见控制 | `switcher` | `false` | **默认关闭**。开启后按下方规则锁定小工具 |
| `zsr_widget_locked` | 需登录后可见的小工具 | `multicheck` | `array()` | 键为 id_base，值为小工具显示名，存储于**独立 option** `zsr_widget_locked`。**不写入 `zibll_options`** |
| `zsr_widget_visitor_action` | 未登录访客看到什么 | `radio` | `placeholder` | `placeholder` = 输出登录引导占位（复用 `zib_get_user_singin_page_box()`，`inc/widgets/widget-user.php:38`）；`hidden` = 整块不输出；`upgrade` = 输出升级引导（复用 `zib_get_user_card_box()`，`inc/widgets/widget-user.php:26`） |
| `zsr_widget_admin_bypass` | 管理员不受限制 | `switcher` | `true` | 开启后 `current_user_can('manage_options')` 的用户始终可见全部小工具，便于排查 |
| `zsr_widget_hide_title` | 占位时隐藏原标题 | `switcher` | `true` | `placeholder` 模式下是否保留小工具原标题 |
| `zsr_widget_exclude` | 排除的小工具 | `multicheck` | `array()` | 硬编码排除名单（如 `widget_ui_search`、`widget_ui_user`），避免把登录/搜索类小工具锁死导致前台不可用 |

### 5.3 独立存储的选项（不进入 CSF 主option）

| option 键 | 数据结构 | 读写函数 | 说明 |
| --- | --- | --- | --- |
| `zsr_widget_locked` | `array( 'zib_widget_ui_main_post' => '1', 'widget_ui_search' => '1' )` | `get_option()` / `update_option()` | 需求明确要求「保存为独立配置项」。用扁平 map 而非嵌套，便于 O(1) 判断 |
| `zsr_version` | `string` | `update_option()` | 插件版本号，用于升级迁移 |
| `zsr_db_version` | `int` | `update_option()` | 数据结构版本，用于增量迁移 |
| `zsr_page_id` | `int` | `update_option()` | 与 CSF 字段 `zsr_page_id` 同步的镜像，避免设置页未保存时丢失 |

> **为何不放入 `zibll_options`**：主题设置页有「自动备份（保留 20 条，`zibll_options_backup`）」与「一键重置」功能（`inc/options/options.php:199-236`）。小工具锁定配置属于插件数据，若混入 `zibll_options`，站点误操作「重置主题设置」会连带清空，属于严重边界情况。详见 §11.3 EC-11。

---

## 6. 前端交互流程与页面结构

### 6.1 页面实现方式：页面模板（非短代码）

**关键约束（实测）**：Zibll 主题**全篇未使用 `template_include` 过滤器**（全主题检索 `template_include` 为 0 处）。主题的所有独立页（用户中心、消息中心、跳转页、论坛、商城）均采用「`template_redirect` + 修正查询属性 + `load_template()`」模式，参见 `zib_gophp_template`（`inc/functions/zib-theme.php:2020-2030`）：

```php
// inc/functions/zib-theme.php:2020-2030
$wp_query->is_home = false;
$wp_query->is_page = true;//将该模板改为页面属性，而非首页
$template          = get_theme_file_path('/go.php');
load_template($template);
exit;
...
add_action('template_redirect', 'zib_gophp_template', 5);
```

**因此插件不能依赖 `template_include` 接管模板**。采用与主题一致的双轨方案：

**方案 A（主）— 页面模板 + 自动建页**

```php
// inc/frontend/page-router.php
add_action('zib_require_end', 'zsr_register_page', 20);   // inc/inc.php:102
function zsr_register_page()
{
    if (!function_exists('zib_get_template_page_url')) { return; }
    if (!_pz_pick('zsr_enable', true)) { return; }

    $tpl = 'zsr-submissions.php';
    $url = zib_get_template_page_url($tpl, array(
        array(_x_pick('投稿与审核', 'zsr'), 'submissions'),
    ));
    // zib_get_template_page_url() 内部：查 postmeta _wp_page_template = $tpl
    // 找不到则 wp_insert_post() 建页 + update_post_meta('_wp_page_template', $tpl)
    // 结果被 wp_cache 缓存在 'page_url' group（inc/dependent.php:105-167）
    update_option('zsr_page_id', url_to_postid($url));
}
```

模板文件头（`templates/zsr-submissions.php`）：

```php
<?php
/**
 * Template Name: 子比-投稿与审核
 * Description:   前台提交稿件、查看审核状态、审核他人稿件（非短代码实现）
 * Version:       1.0
 */
if (!defined('ABSPATH')) { exit; }
// 权限前置校验（模板级兜底，防止直接访问模板文件）
if (!zsr_user_can_access_page()) {
    get_template_part('template/content-404');
    get_footer();
    exit;
}
get_header();
get_template_part('zsr/view-submit');      // 提交/编辑表单
get_template_part('zsr/view-my-list');     // 我的投稿
if (zsr_current_user_can('zsr_review')) {
    get_template_part('zsr/view-review');  // 审核台
}
get_footer();
```

**方案 B（兜底）— `template_redirect` + `load_template()`**

当自动建页失败（`zib_get_template_page_url()` 返回 `false`，如 `wp_insert_post()` 被禁用）时启用，保证审核入口不失效：

```php
add_action('template_redirect', 'zsr_fallback_virtual_page', 5);
function zsr_fallback_virtual_page()
{
    if (get_option('zsr_page_id')) { return; }// 已建页则不接管
    if (!zsr_is_our_query()) { return; }
    zsr_boot_page();// 复制方案 A 的模板主体
    exit;
}
```

**URL 形态**：

| 视图 | URL | 说明 |
| --- | --- | --- |
| 投稿与审核主页 | `https://site.com/submissions/` | 自动创建的 WP 页面 |
| 提交稿件 | `…/submissions/?view=submit` | 视图参数 |
| 编辑稿件 | `…/submissions/?view=edit&post_id=123` | 需`zsr_cap_submit` 且为本人稿件 |
| 稿件详情 | `…/submissions/?view=detail&post_id=123` | 作者本人或有 `zsr_review_others` |
| 审核台 | `…/submissions/?view=review&status=pending` | 需 `zsr_review` |
| 审核记录 | `…/submissions/?view=history` | 需 `zsr_review` |

**视图路由**：`template_redirect` 阶段读取 `$_GET['view']`，白名单校验（`in_array($view, $allowed, true)`），未知值回落`submit`。**禁止**把 `view` 直接拼入文件路径或 `include`。

### 6.2 页面 DOM 结构

**复用主题 `page.php` 的骨架**（实测 `page.php:35-77`），保证与主题视觉一致：

```html
<!-- get_header() 输出 <body class="..."> -->
<main class="container page-id-{PAGE_ID}">
  <div class="content-wrap">
    <div class="content-layout">

      <!-- ① 视图切换 Tab，复用 .index-tab/.tab-content -->
      <div class="index-tab">
        <a class="active" href="?view=submit">提交稿件</a>
        <a href="?view=my">我的投稿 <span class="badge">3</span></a>
        <a href="?view=review">审核台 <span class="badge">12</span></a>
      </div>

      <!-- ② 表单视图 -->
      <article class="article main-bg theme-box box-body radius8 main-shadow">
        <form class="zsr-form" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label class="muted-2-color">标题</label>
            <input class="form-control" type="text" name="post_title" required>
          </div>
          <div class="form-group">
            <label class="muted-2-color">分类</label>
            <select class="form-control" name="post_category[]" multiple>…</select>
          </div>
          <div class="form-group">
            <label class="muted-2-color">正文</label>
            <!-- 复用主题编辑器，勿自研富文本 -->
            <?php wp_editor($content, 'zsr_content', zsr_editor_args()); ?>
          </div>
          <input type="hidden" name="action" value="zsr_submit">
          <?php wp_nonce_field('zsr_submit', '_wpnonce', false, false); ?>
          <div class="mt20 but-average">
            <button type="submit" class="but wp-ajax-submit">提交审核</button>
          </div>
        </form>
      </article>

      <!-- ③ 列表视图 -->
      <article class="article main-bg theme-box box-body radius8 main-shadow">
        <div class="posts-mini-lists">
          <div class="posts-mini">
            <div class="posts-mini-con flex xx flex1 jsb">
              <div>
                <a class="absolute" href="?view=detail&post_id=1">稿件标题</a>
                <div class="muted-2-color em09">2026-10-05 12:00 · 分类</div>
              </div>
              <div>
                <!-- 状态徽章：复用 .badg 或 .avatar-badge -->
                <span class="badge badge-warning">待审核</span>
              </div>
            </div>
          </div>
        </div>
        <!-- 分页：复用 zib_get_ajax_number_paginate() -->
      </article>

    </div>
  </div>
  <?php get_sidebar(); ?>
</main>
```

**结构对齐要点**：

| 插件元素 | 复用主题结构 | 主题出处 |
| --- | --- | --- |
| 外层 `<main>` / `content-wrap` / `content-layout` | 与 `page.php:35-40` 完全一致 | `page.php` |
| 内容卡片 | `article.main-bg.theme-box.box-body.radius8.main-shadow` | `page.php:61` |
| 表单控件 | `.form-control` + `.form-group` | `css/main.css:2606` |
| 按钮 | `.but`（**注意不是 `.btn`**）+ `.wp-ajax-submit` | `css/main.css:1665`；`action/new_posts.php:48` |
| 按钮组 | `.but-average` / `.but-group` | `css/main.css:1797,1813` |
| 列表 | `.posts-mini-lists` / `.posts-mini` | `inc/widgets/widget-posts.php:1271` |
| 空状态 | `zib_get_ajax_null($text, $margin)` | `inc/functions/functions.php:2421` |
| 骨架屏 | `zib_get_post_placeholder($type, $count)` | `inc/widgets/widget-posts.php:1245` |
| AJAX 分页 | `zib_get_ias_ajaxpager($args)` / `zib_get_ajax_number_paginate()` | `inc/functions/functions.php:2458, 2212` |
| 侧边栏 | `get_sidebar()`（内部 `zib_is_show_sidebar()` 判断） | `sidebar.php:14-45` |
| 登录引导 | `zib_get_user_singin_page_box()` | `inc/widgets/widget-user.php:38` |

### 6.3 投稿提交流程

```
用户点击「提交审核」
   │
   ├─ POST /wp-admin/admin-ajax.php  action=zsr_submit
   ││
   ├─▶① zib_ajax_verify_nonce()            // action/function.php:721 → :280
   │     └─ nonce action = 'zsr_submit'（与 AJAX action 同名，主题范式）
   │        失败 → zib_send_json_error('环境异常…') + exit
   │
   ├─▶ ② zib_current_user_can('zsr_submit') // user-cap.php:227
   │     └─ 失败 → zib_send_json_error('抱歉您的权限不足') + exit
   │
   ├─▶ ③ zib_user_is_ban() 排除封禁用户      // user-ban.php:19
   │     └─ 命中 → zib_send_json_error('账号已被封禁')
   │
   ├─▶ ④ 主题限频校验
   │     └─ 挂 zib_pre_insert_post（zib-theme.php:896 为 pre_insert_post 钩子）
   │        由 zib_brush_limit_post() 执行主题既有限频，插件不重复实现
   │
   ├─▶ ⑤ 构造 $postarr
   │     post_type   = 'post'
   │     post_status = 'pending'           // 走审核，与 action/new_posts.php:205 一致
   │     post_author = get_current_user_id()
   │
   ├─▶ ⑥ do_action('zib_pre_insert_post', $postarr)   // action/new_posts.php:218
   │     └─ 主题插件可在此拦截，插件挂 zsr_pre_insert_post_filter 二次兜底
   │
   ├─▶ ⑦ wp_insert_post($postarr, 1)                   // action/new_posts.php:221
   │
   ├─▶ ⑧ 写入审核元数据
   │     post_meta:
   │       zsr_state           = 'pending'
   │       zsr_submitted_at    = current_time('mysql')
   │       zsr_submit_count     = 1
   │       zsr_version         = 2                 // 编辑结构版本
   │
   ├─▶ ⑨ do_action('new_posts_pending', $post)          // action/new_posts.php:261
   │     └─ 主题已挂 zib_email_newpost_contribution_to_admin（message/functions/new.php:164）
   │        插件挂 zsr_notify_reviewers 提醒审核人（受 zsr_notify_approver 控制）
   │
   └─▶ ⑩ zib_send_json_success([
           'msg'    => '内容已提交，正在等待审核',// 复用主题文案
           'goto'   => 详情页 URL,
           'post_id'=> $post_id
         ])
```

**编辑已提交稿件**：`action=zsr_update`，要求 `post_author == 当前用户` 且 `post_status IN ('draft','pending')`，且（`zsr_state` 为 `draft`/`rejected`/`returned`）。保存后 `post_status` 重置为 `pending`、`zsr_submit_count + 1`，并清空上一次的审核意见（保留在 `zsr_review_history` 中）。

### 6.4 审核流程

**完整实现范式参考**：主题论坛已有同类实现 `zib_bbs_ajax_plate_or_posts_audit()`（`inc/functions/bbs/action/ajax-posts.php:484-541`），插件沿用其四步结构（取参 → 验nonce → 验权限 → 改状态）：

```
审核人在审核台点击「通过 / 驳回 / 退回」
   │
   ├─ 驳回/退回时先弹意见输入框
   │     └─ HTML：复用主题 .but-average + textarea.form-control
   │        提交按钮 class="but c-red wp-ajax-submit"
   │
   ├─ POST /wp-admin/admin-ajax.php  action=zsr_review
   │     参数：post_id(int)、method(approve|reject|return)、msg(string)
   │
   ├─▶ ① zib_ajax_verify_nonce()          // nonce action = 'zsr_review'
   │
   ├─▶ ② zib_current_user_can('zsr_review')            // 无权 → 拒绝
   │     zib_current_user_can('zsr_review_others')     // 审他人稿件时二次校验
   │     post_author == get_current_user_id() && !_pz('zsr_allow_self_review') → 拒绝
   │
   ├─▶ ③ method 是否在 zsr_actions 白名单内
   │     └─ 否 → zib_send_json_error('该审核动作未被启用')
   │
   ├─▶ ④ 状态前置校验：post_status 必须为 'pending'
   │     └─ 否 → zib_send_json_error('该稿件不处于待审核状态，请刷新后重试')
   │
   ├─▶ ⑤ 意见长度与必填校验
   │     reject && zsr_reject_reason_required && !mb_strlen(trim(msg))
   │        → zib_send_json_error('请填写驳回原因或修改建议')  // 复用论坛文案
   │
   ├─▶ ⑥ 计算目标状态
   │     approve → zsr_approve_to_status（默认 'publish'）
   │     reject  → zsr_reject_to_status（默认 'pending'）
   │     return  → zsr_return_to_status（默认 'draft'）
   │
   ├─▶ ⑦ 写审核日志（append，不覆盖）
   │     post_meta 'zsr_review_history'（数组，序列化）：
   │       [ time, reviewer_id, method, from_status, to_status, msg ]
   │     post_meta 'zsr_state'          = approve|rejected|returned
   │     post_meta 'zsr_reviewed_at'    = current_time('mysql')
   │     post_meta 'zsr_reviewed_by'    = reviewer_id
   │     post_meta 'zsr_reject_reason'  = mb_substr($msg, 0, zsr_reason_maxlength)
   │
   ├─▶ ⑧ 目标状态非 pending 时才执行 wp_update_post（对齐论坛 reload 语义）
   │     $reloaded = ($target !== $post->post_status);
   │     wp_update_post(['ID' => $post_id, 'post_status' => $target])
   │
   ├─▶ ⑨ 通知作者
   │     approve → zib_newmsg_publish_to_pending 不适用（那是驳回）；
   │              插件复用主题站内信函数，标题「您发布的稿件已通过审核：[%s]」
   │     reject  → 复用 zib_newmsg_publish_to_pending($post)（message/functions/new.php:317）
   │     return  → 插件自有通知，文案「您发布的稿件被退回修改」
   │     双通道受 zsr_notify_channel 控制
   │
   └─▶ ⑩ zib_send_json_success([
           'msg'    => '已通过' / '已驳回并通知作者' / '已退回作者修改',
           'reload' => $reloaded,
           'hide_modal' => true
         ])
```

> **对 `zib_newmsg_publish_to_pending()` 的注意事项**（实测 `inc/functions/message/functions/new.php:317-347`）：该函数第 320 行有 `if (empty($post->post_author) || empty($_REQUEST['msg_s'])) { return; }` —— **它硬依赖 `$_REQUEST['msg_s']` 非空**。插件调用时必须显式 `$_REQUEST['msg_s'] = 1;`，否则通知会静默失败。同文件第 333 行还有「作者自己驳回的不通知」逻辑（`$user_id == get_current_user_id()` 直接 return），因此**自我驳回场景插件需自行补发通知**。

### 6.5 状态机

```
                    ┌──────────────────────────────────────┐
                    │                draft                 │
                    │  草稿：仅作者可见，可自由编辑          │
                    └───────┬──────────────────────┬───────┘
              提交/重新提交 │                退回 │ return
                    ┌───────▼──────────────┐       │
                    │      pending         │◄──────┘
                    │  待审核：进入审核队列  │  return(→pending)
                    └───┬────────┬─────────┘
       approve  │        │        │  驳回 reject
 （→publish）  │        │        │（→pending + zsr_state=rejected）
                │        │        │        │
     ┌──────────▼──┐  ┌──▼──────────────┐  │
     │   publish   │  │    rejected     │◄─┘
     │  已发布     │  │ 已驳回（留队列） │
     └──────┬──────┘  └───┬──────────────┘
            │撤稿│        │作者修改后重新提交
            └────┼────────┘
                 ▼
              pending
```

**状态到存储的映射表**（实现必须严格遵守）：

| 展示状态 | `post_status` | `post_meta.zsr_state` | 队列可见 | 作者可编辑 | 前台可访问 |
| --- | --- | --- | --- | --- | --- |
| 草稿 | `draft` | `draft` | 否 | 是 | 仅作者 |
| 待审核 | `pending` | `pending` | 是 | 是 | 仅作者 + 审核人 |
| 已驳回 | `pending` | `rejected` | 是 | 是 | 仅作者 + 审核人 |
| 已退回 | `draft` | `returned` | 否 | 是 | 仅作者 |
| 已通过 | `publish` | `approved` | 否 | 否（走 WP 后台） | 公开 |
| 已发布（免审直发） | `publish` | `''`（无插件记录） | 否 | 否 | 公开 |
| 回收站 | `trash` | 原值保留 | 否 | 否 | — |

> **设计要点**：「已驳回」与「待审核」共用 `post_status = pending`，靠 `zsr_state` 区分。这样做的原因是：**保持与主题待审队列的兼容性** —— 被驳回的稿件仍会出现在 WP 后台的「待审」列表中，管理员不会漏掉它；同时 `zsr_state = rejected` 让作者在前台看到明确的「已驳回 + 原因」。备选方案（用 `trash` 或自定义 post_status）会破坏主题既有逻辑，故不采用。

### 6.6 小工具控制生效流程

```
浏览器请求任意前台页面
   │
   ├─ WP 渲染到 dynamic_sidebar('home_sidebar') 等
   │   │
   │   ├─ 过滤器 dynamic_sidebar_params（优先级 10）
   │   │    └─ 主题已挂钩 zib_cfswidget_dynamic_sidebar_params（widget-class.php:67）
   │   │       id 含 'fluid' 时自动套 .widget-container
   │   │    └─ 插件挂钩 zsr_widget_gate_sidebar_params（优先级 9999）
   │   │       在 $params[0]['before_widget'] 前插入 zsr 隐藏标记
   │   │
   │   ├─ WP 遍历 $wp_registered_widgets[$widget_id]['callback'] 逐个输出
   │   │    └─ 插件在 widgets_init 后期（优先级 999）替换被锁定小工具的 callback
   │   │       为 zsr_locked_widget_stub()，实现「完全不输出」
   │   │
   │   └─ 若 zsr_widget_visitor_action = 'placeholder'
   │        则 callback 换成 zsr_locked_widget_placeholder()，
   │        输出 zib_get_user_singin_page_box() 引导
   │
   └─ 访客最终看到：登录引导框 / 什么都没有 / 升级引导（由配置决定）
```

**双重保险设计**：`dynamic_sidebar_params` 只能改包裹层，**无法阻止 widget 内部 HTML 输出**。因此真正的「不输出」必须通过替换 `$wp_registered_widgets[$widget_id]['callback']` 实现（这是 WordPress `dynamic_sidebar()` 唯一调用的入口），再叠加 `dynamic_sidebar_params` 做占位样式包裹。两条路径互为补充，任一生效即可达到目的。

---

## 7. 数据存储方案

### 7.1 存储总览

| 数据 | 载体 | 键名 | 生命周期 |
| --- | --- | --- | --- |
| 插件设置 | option（关联数组） | `zsr_options` | 卸载可删 |
| 小工具锁定配置 | option（关联数组） | `zsr_widget_locked` | 卸载可删 |
| 稿件审核状态 | post meta | `zsr_state` | 随稿件 |
| 提交时间 | post meta | `zsr_submitted_at` | 随稿件 |
| 提交次数 | post meta | `zsr_submit_count` | 随稿件 |
| 最近审核时间 | post meta | `zsr_reviewed_at` | 随稿件 |
| 最近审核人 | post meta | `zsr_reviewed_by` | 随稿件 |
| 最近审核意见 | post meta | `zsr_reject_reason` | 随稿件 |
| 审核历史（追加） | post meta（序列化数组） | `zsr_review_history` | 随稿件 |
| 审核锁（防并发） | transient | `zsr_lock_{post_id}` | 60s 自动过期 |
| 权限能力键 | **主题 option** | `zibll_options['user_cap']['zsr_*']` | 卸载时移除 |
| 扩展能力键 | **主题 user_cap** | 同上 | 随主题 |

> **关于是否使用自定义文章类型（CPT）**：本插件**不注册 CPT**。理由：主题的投稿链路完全建立在 `post` 类型之上（`action/new_posts.php:180-208` 构造的 `$postarr` 使用 `post_type => 'post'`，并按`post` 类型走 `post_category`、`wp_insert_post`、主题的 `post_*` meta 体系、`post_newposts_pending_msg` 等）。若改用 CPT，将无法复用主题的分类、标签、SEO、付费、积分、评论、通知等全部既有能力，且需要额外做 CPT → `post` 的转换逻辑。**结论：复用 `post` + post meta 是唯一正确选择。** 用户需求中「自定义文章类型/元数据/选项表的使用」在此明确回答为：**元数据 + 选项表，不用自定义文章类型**，理由如上。

### 7.2 post meta 详细定义

| meta_key | 类型 | 格式 | 写入时机 | 说明 |
| --- | --- | --- | --- | --- |
| `zsr_state` | `string` | `draft`/`pending`/`rejected`/`returned`/`approved` | 提交时 +每次审核 | 与 `post_status` 组合决定展示状态，见 §6.5 |
| `zsr_submitted_at` | `string` | `Y-m-d H:i:s`（站点时区） | 提交时 | 用 `current_time('mysql')` 而非 `date()`，保证时区一致 |
| `zsr_submit_count` | `int` | 整数 | 提交时 `+1` | 统计重新提交次数 |
| `zsr_reviewed_at` | `string` | `Y-m-d H:i:s` | 审核时 | 冷却窗口计算依据 |
| `zsr_reviewed_by` | `int` | 用户 ID | 审核时 | 「我审核过的」筛选依据 |
| `zsr_reviewer_name` | `string` | 显示名快照 | 审核时 | 防止用户改名/注销后审核记录不可读 |
| `zsr_reject_reason` | `string` | 已截断文本 | reject/return 时 | 长度受 `zsr_reason_maxlength` 限制 |
| `zsr_review_history` | `array`（序列化） | 见下 | 审核时 append | **只追加，不覆盖** |
| `zsr_version` | `int` | 整数 | 首次提交 | 稿件数据结构版本，用于未来迁移 |

`zsr_review_history` 单条结构：

```php
array(
    'time'         => '2026-10-05 14:30:00',   // current_time('mysql')
    'reviewer_id'  => 12,                      // 用户 ID
    'reviewer_name'=> '张三',                   // 显示名快照
    'method'       => 'reject',                // approve|reject|return
    'from_status'  => 'pending',
    'to_status'    => 'pending',
    'msg'          => '标题过于宽泛，请补充具体版本号',// 已 sanitize
);
```

**上限控制**：数组超过 50 条时保留最早 1 条（首次提交信息）+ 最近 49 条，防止 meta 无限膨胀。

### 7.3 option 数据结构

`zsr_options`（CSF 序列化的关联数组，与主题 `zibll_options` 结构一致）：

```php
array(
    'zsr_enable'                => true,
    'zsr_enable_submit'         => true,
    'zsr_enable_review'         => true,
    'zsr_page_id'               => 156,
    'zsr_page_slug'             => 'submissions',
    'zsr_menu_label'            => '我的投稿',
    'zsr_show_menu_item'        => true,
    'zsr_menu_position'         => '0',

    'zsr_cap_submit'            => array('logged' => true),
    'zsr_cap_review'            => array('moderator' => true, 'plate_author' => true, 'cat_moderator' => true),
    'zsr_cap_review_others'     => array('moderator' => true, 'plate_author' => true, 'cat_moderator' => true),
    'zsr_review_self_only'      => false,

    'zsr_actions'               => array('approve', 'reject', 'return'),
    'zsr_approve_to_status'     => 'publish',
    'zsr_approve_keep_audit'    => false,
    'zsr_reject_to_status'      => 'pending',
    'zsr_return_to_status'      => 'draft',
    'zsr_reject_reason_required'=> true,
    'zsr_return_reason_required'=> false,
    'zsr_reason_maxlength'      => 200,
    'zsr_allow_self_review'     => false,
    'zsr_allow_re_review'       => true,
    'zsr_re_review_window'      => 0,

    'zsr_notify_author'         => true,
    'zsr_notify_channel'        => array('msg', 'email'),
    'zsr_notify_approver'       => false,
    'zsr_notify_on_return'      => true,
    'zsr_notify_include_content'=> true,

    'zsr_widget_enable'         => false,   // ← 默认关闭
    'zsr_widget_locked'         => array(), // ← 镜像显示，实际生效读 zsr_widget_locked option
    'zsr_widget_visitor_action' => 'placeholder',
    'zsr_widget_admin_bypass'   => true,
    'zsr_widget_hide_title'     => true,
    'zsr_widget_exclude'        => array(),
);
```

`zsr_widget_locked`（**独立 option，实际生效依据**）：

```php
array(
    'zib_widget_ui_main_post'  => '1',   // 文章列表模块
    'zib_widget_ui_hot_posts'  => '1',   // 热榜文章
    'zib_shop_widget_ui_product_lists' => '1',// 商品列表
);
```

> **双写策略**：CSF 字段 `zsr_widget_locked` 存于 `zsr_options`（供设置页回显），同时镜像到独立 option `zsr_widget_locked`（供前台O(1) 读取）。保存设置页时（挂 `csf_zsr_options_saved`，框架在 `admin-options.class.php:392` 触发）执行同步。读取时**只认独立 option**，避免每次前台渲染都加载整个 `zsr_options`。二者不一致时以独立 option 为准，并在设置页显示提示。

### 7.4 权限能力的持久化

`user_cap` 存在于主题 option `zibll_options` 内（`inc/dependent.php:195-228`的 `_pz()` 读取）。插件写入时：

```php
// 写入：合并而非覆盖
$caps = (array) _pz('user_cap', array());
$caps['zsr_submit'] = $settings['zsr_cap_submit'];
// ... 其余能力键
_spz('user_cap', $caps);

// 卸载：仅移除自己的键
$caps = (array) _pz('user_cap', array());
foreach (array('zsr_submit','zsr_review','zsr_review_others','zsr_manage') as $k) {
    unset($caps[$k]);
}
_spz('user_cap', $caps);
```

**幂等保证**：`zsr_register_capabilities()` 先 `isset()` 判断再写，重复调用不会覆盖站点已做的权限调整。

### 7.5 索引与查询性能

| 查询场景 | 查询方式 | 性能考量 |
| --- | --- | --- |
| 我的投稿列表 | `WP_Query` + `author` + `post_status IN (...)` | 走 `post_author` 索引 |
| 审核台队列 | `WP_Query` + `post_status='pending'` + `post_type='post'` | 走 `post_status` 索引；深分页时用 `no_found_rows => true` + 独立 `COUNT` 限制深度 |
| 审核记录 | `WP_Query` + `meta_query('zsr_reviewed_by' = 当前用户)` | **meta 无索引**，需 `LIMIT` 限制在 100 条内 |
| 小工具已启用列表 | `wp_get_sidebars_widgets()` + `get_option('widget_'.$id_base)` | 全部走对象缓存，无DB 查询 |
| 锁定判断 | `isset($locked[$id_base])` | 纯内存，O(1) |

**优化决策**：
1. 审核台队列的 `post_status` 查询在稿件量 > 5000 的站点可能变慢 → 配置项 `zsr_queue_cache_ttl`（默认 60s）配合 `wp_cache`（group `zsr`）缓存待审数量，供Tab 徽章使用。
2. 「审核记录」不做分页优化，限制 50 条，因为这是低频视图。

### 7.6 数据迁移与卸载

| 版本 | 迁移内容 |
| --- | --- |
| `zsr_db_version` 1 → 2 | 引入 `zsr_review_history` 数组结构；若检测到旧版扁平 `zsr_review_log` 字符串，转换为数组首条 |

**`uninstall.php` 清理清单**：

| 类型 | 键 | 是否删除 |
| --- | --- | --- |
| option | `zsr_options` | ✅ 删除 |
| option | `zsr_widget_locked` | ✅ 删除 |
| option | `zsr_version` / `zsr_db_version` / `zsr_page_id` | ✅ 删除 |
| option | `zibll_options['user_cap']` 中的 `zsr_*` 键 | ✅ **只删自己的键** |
| post meta | 所有 `zsr_*` meta | ❌ **不删除**（稿件属于站点内容，卸载插件不应破坏内容数据） |
| WP 页面 | 插件自动创建的投稿页| ⚠️ **不删除**，改为 `wp_trash_post()`，让管理员在回收站中确认 |
| transients | `zsr_*` | ✅ 删除 |
| 对象缓存 | `wp_cache_flush_group('zsr')` |✅ 清理 |

> **决策说明**：投稿页不自动永久删除。理由是管理员可能已经把该页面加入菜单、写了外链、或做了 SEO 落地页。直接删除会造成站点 404 与死链，属于破坏性行为。改为移入回收站 + 在卸载提示中告知，是更安全的默认。

---

## 8. 与 Zibll 主题的集成点与兼容性要求

### 8.1 兼容性基线

| 项 | 要求 |
| --- | --- |
| 目标主题 | Zibll（子比）V9.0（`style.css:10`） |
| 支持版本范围 | V8.1 ~ V9.0（向上兼容，依赖的都是稳定函数） |
| 最低 WordPress | 5.0（对齐主题 `style.css:9` `Requires at least: 5.0`） |
| 最低 PHP | 7.0（对齐主题 `style.css:9` `Requires PHP: 7.0-8.5`） |
| 主题检测 | 未检测到 Zibll 时插件不激活，后台显示友好提示，不白屏 |
| 必需函数检测 | 激活时检查 `_pz()`、`zib_current_user_can()`、`zib_send_json_success()`、`zib_get_template_page_url()`、`CSF` 类是否存在；缺失则拒绝激活并列出缺失项 |

### 8.2 依赖的主题 API 清单（必须复用的函数）

| 函数 / 类 | 位置 | 用途 | 缺失后果 |
| --- | --- | --- | --- |
| `_pz($name, $default, $subname)` | `inc/dependent.php:195` | 读主题设置 | **致命** |
| `_spz($name, $value)` | `inc/dependent.php:216` | 写主题设置 | **致命** |
| `zib_get_template_page_url($tpl, $args)` | `inc/dependent.php:105` | 自动建页取URL | 降级为方案 B |
| `zib_current_user_can($cap, ...$args)` | `inc/functions/user/user-cap.php:227` | 权限判定 | **致命** |
| `zib_user_is_ban($user_id)` | `inc/functions/user/user-ban.php:19` | 封禁用户排除 | 跳过封禁检查 |
| `zib_user_level($id)` / `zib_get_user_level($id)` | `inc/functions/user/user-level.php:199` | 等级身份判定 | 降级 |
| `zib_get_user_vip_level($id)` | `zibpay/functions/zibpay-vip.php:853` | 会员身份判定 | 降级 |
| `zib_is_user_auth($id)` | `inc/functions/user/user-auth.php:15` | 认证身份判定 | 降级 |
| `zib_is_close_sign()` | `inc/functions/zib-user.php:269` | 关闭注册时兜底 | 降级 |
| `zib_send_json_success($data,$type)` | `action/ajax.php:39` | AJAX 成功返回 | **致命**（须自备替代） |
| `zib_send_json_error($data,$type)` | `action/ajax.php:14` | AJAX 失败返回 | **致命** |
| `zib_ajax_verify_nonce($action,$name)` | `action/function.php:721` | nonce 校验 | 须自备 |
| `zib_ajax_notice_modal($type,$msg)` | `inc/functions/functions.php:2382` | 前台弹窗提示 | 降级为页面提示 |
| `zib_get_ajax_null($text,$margin)` | `inc/functions/functions.php:2421` | 空状态 | 降级为自绘 |
| `zib_get_ajax_number_paginate(...)` | `inc/functions/functions.php:2212` | 数字分页 | 降级为 `paginate_links()` |
| `zib_get_post_placeholder($type,$count)` | `inc/widgets/widget-posts.php:1245` | 骨架屏 | 降级为无占位 |
| `zib_get_user_singin_page_box()` | `inc/widgets/widget-user.php:38` | 登录引导 | 降级为自绘 |
| `zib_str_cut($str,$start,$len,$end)` | 主题工具函数 | 摘要截断 | 用 `mb_substr` |
| `zib_newmsg_publish_to_pending($post)` | `inc/functions/message/functions/new.php:317` | 驳回通知 | 自建通知 |
| `class ZCSF` | `inc/csf-framework/classes/zib-csf.class.php:16` | 表单渲染（可选） | 降级为原生表单 |
| `class CSF` | `inc/codestar-framework/classes/setup.class.php` | 设置页 | **致命** |
| `add_action('zib_require_end')` | `inc/inc.php:102` | 加载完成钩子 | 降级为 `after_setup_theme` |

> **重要提醒**：**不要使用 `zib_get_option($key, $default, $filter)`**。该函数真实签名只有**一个参数**且**不读主题设置**（`inc/dependent.php:271-291`），它只用于读「聚合存储」中的散落 option。主题源码中`inc/options/options.php:323` 甚至有一条注释警告：`$up = get_option('zibll_new_version'); //不能使用zib_get_option`。

### 8.3 钩子挂载清单

| 钩子 | 类型 | 优先级 | 回调 | 用途 |
| --- | --- | --- | --- | --- |
| `zib_require_end` | action | 20 | `zsr_register_page` | 自动创建前台页面（主题所有函数已就绪） |
| `after_setup_theme` | action | 20 | `zsr_admin_options` | 注册后台设置页 |
| `after_setup_theme` | action | 30 | `zsr_load_textdomain` | 加载插件翻译 |
| `admin_init` | action | 5 | `zsr_register_capabilities` | 注册能力键 |
| `widgets_init` | action | 999 | `zsr_lock_widgets` | 替换被锁定小工具的 callback |
| `dynamic_sidebar_params` | filter | 9999 | `zsr_widget_gate_sidebar_params` | 包裹层占位样式 |
| `template_redirect` | action | 5 | `zsr_fallback_virtual_page` | 兜底虚拟页 |
| `template_redirect` | action | 6 | `zsr_enqueue_assets` | 精确加载前端资源 |
| `wp_nav_menu_items` | filter | 10 | `zsr_add_nav_menu_item` | 注入前台菜单入口 |
| `admin_notices` | action | 10 | `zsr_admin_notice` | 未检测到主题时的提示 |
| `csf_zsr_options_saved` | action | 10 | `zsr_sync_widget_lock_option` | 同步独立 option |
| `is_can_roles` | filter | 10 | `zsr_filter_can_roles` | 让社区身份获得审核权 |
| `zib_user_can` | filter | 20 | `zsr_filter_user_can` | 能力键兜底与扩展 |
| `new_posts_pending` | action | 10 | `zsr_notify_reviewers` | 提醒审核人（可复用待审计数） |
| `zib_pre_insert_post` | action | 10 | `zsr_pre_insert_post_filter` | 提交前兜底校验 |

### 8.4 不得触碰的主题区域

| 路径 / 机制 | 原因 |
| --- | --- |
| `inc/code/*.php`（`require.php`、`action.php`、`code.php`、`file.php`、`tool.php`、`update.php`、`aut.php`、`new_aut.php`） | **全部经 `gzinflate()` + `eval()` 混淆**（`inc/code/require.php:1` 起为载荷，`:52-62` 为 `eval()`）。无法静态审计、无法静态分析钩子、主题更新后会变化。**插件绝不能依赖该目录的任何内部实现** |
| `inc/csf-framework/classes/*` | 主题通过 `csf_override` 过滤器（`inc/options/options.php:166-171`）覆盖了 CSF 内核类，行为与上游 2.2.0 不同。插件只使用 `CSF::createOptions()` / `CSF::createSection()` 公开 API，不继承 `CSF_Options` 等内部类 |
| `zibll_options` 主 option | 含主题全部设置与 20 条自动备份。除`user_cap` 中的插件自有键外，**任何情况下不得写入** |
| 主题的 `new_posts_pending` 邮件通知 | 主题已挂 `zib_email_newpost_contribution_to_admin`（`inc/functions/message/functions/new.php:164`）。插件**追加**提醒而不替换，避免管理员收不到邮件 |
| `action/new_posts.php` 的 `post_status` 判定逻辑 | 插件的审核台消费 `pending` 状态，若改写该判定会导致主题与插件双重审核 |
| 小工具的 `widget()` 渲染实现 | 51 个小工具的渲染逻辑分布在 10+ 文件，改动即回归风险 |
| 主题的 `unregister_d_widget()` | 主题主动注销了 7 个 WP 默认小工具（`inc/widgets/widget-index.php:23-33`），插件不得恢复它们 |

### 8.5 小工具控制的具体实现

**枚举「已启用」小工具**（`inc/widget/enumerator.php`）：

```php
function zsr_get_enabled_widgets()
{
    // ① 主题已提供：取全部已注册 id_base（inc/widgets/widget-import.php:59）
    $registered = function_exists('zib_wie_get_registered_id_bases')
        ? zib_wie_get_registered_id_bases()
        : zsr_fallback_registered_id_bases();

    // ② 取已放置到侧边栏的实例（$sidebars_widgets 的 wp_parse_args 展开结果）
    $sidebars = wp_get_sidebars_widgets();
    unset($sidebars['wp_inactive_widgets']);//未启用的不算

    $result = array();
    foreach ($sidebars as $sidebar_id => $widget_ids) {
        foreach ((array) $widget_ids as $widget_id) {
            if (!is_string($widget_id) || $widget_id === '') { continue; }
            // 解析 'zib_widget_ui_main_post-2' → id_base + 实例号
            if (!preg_match('/^(.+)-(\d+)$/', $widget_id, $m)) { continue; }
            $id_base = $m[1];
            if (!isset($registered[$id_base])) { continue; }  // 未注册（如主题已注销）
            $result[$id_base][$sidebar_id][] = (int) $m[2];
        }
    }
    // 每个 id_base 汇总实例数与所在侧边栏，供后台列表展示
    return $result;
}
```

**关键事实与陷阱**（均来自实测）：

| 事实 | 出处 | 对实现的影响 |
| --- | --- | --- |
| 「已注册」用 `$wp_widget_factory->widgets`，**不是** `$wp_registered_widgets` | `inc/widgets/widget-import.php:59-73` | 沿用主题写法 |
| 「已启用」必须读 `wp_get_sidebars_widgets()`，光看注册表是错的 | `widget-import.php:87` | 见上方代码 |
| 实例 ID 形如 `{id_base}-{数字}`，解析用正则 `/^(.+)-(\d+)$/` | `widget-import.php:15-33` | 复用 `zib_wie_parse_widget_id()` |
| 配置存于 `get_option('widget_'.$id_base)`，内含 `_multiwidget` 字符串键 | `widget-import.php:102-123` | 遍历时须用 `is_numeric($k)` 排除 `_multiwidget`（主题在 `:45-49` 如此处理） |
| CSF 轨 `is_show()` 已有过滤器 `widget_is_show_{id_base}` | `widget-options.class.php:526` | 插件**优先**挂此过滤器实现「不输出」，最干净 |
| 但旧轨（9 个小工具）不经过此过滤器 | 旧轨 `widget()` 无 `is_show` 调用 | 旧轨必须用 callback 替换兜底 |
| `widget_ui_notice` 在两轨**重名** | `widget-more.php:251`（旧轨，已注释）与 `:3480`（CSF 轨，已启用） | 按 id_base 匹配时无法区分；由于旧轨已注释不注册，实际只有 CSF 轨生效 |
| id_base 前缀**不统一**：新模块 `zib_widget_ui_*`、移植的 `widget_ui_*`、商城 `zib_shop_*`、社区 `zib_bbs_*` | `inc/functions/bbs/widgets/`、`inc/functions/shop/widgets/` | **不可用前缀推断类型**，必须读注册对象 |
| CSF 轨注册时机是 `after_setup_theme`（`Zib_CFSwidget::create()`），而 `CSF::register_widgets()` 在 `widgets_init` | `setup.class.php:65-86` | 插件的枚举逻辑必须在 `widgets_init` 之后（即 `widgets_init` 优先级 999 或 `admin_init`）执行 |
| `CSF::register_widgets()` 内部 `new WP_Widget_Factory()` 而非用全局 | `setup.class.php:73` | 不能依赖 `get_widget_object()`；主题基类注释已明确警告（`widget-options.class.php:81-95`），插件须自行遍历 `$wp_widget_factory->widgets` |
| `zib_wie_widget_title_for_id_base()` 内有遗留 `error_log(print_r(...))` | `widget-import.php:384` | **插件不要调用该函数**，否则每次都dump 整个 widget 工厂到错误日志 |
| `zib_register_sidebar()` 的 `before_widget` = `<div class="zib-widget %2$s">` | `inc/widgets/widget-index.php:173-188` | 占位替换时须保持同层结构，否则样式错乱 |
| id 含 `fluid` 的侧边栏会被自动套一层 `.widget-container` | `inc/widgets/widget-class.php:67-79` | 替换 callback 时不需重复包裹，否则双层 |

**生效实现（双保险）**：

```php
// 优先路径：CSF 轨官方过滤器（inc/csf-framework/classes/widget-options.class.php:526）
//   apply_filters( 'widget_is_show_' . $this->unique, $show_class, $args, $instance )
// 插件对每个被锁定的 id_base 挂一个过滤器，返回 false 即完全不输出。
//   → add_filter('widget_is_show_zib_widget_ui_main_post', 'zsr_block_widget', 10, 3);

/**
 * @return mixed false = 阻止输出
 */
function zsr_block_widget($show_class, $args, $instance)
{
    return is_user_logged_in() ? $show_class : false;
}

// 兜底路径：替换 callback（对旧轨 9 个小工具同样有效）
add_action('widgets_init', 'zsr_lock_widget_callbacks', 999);
function zsr_lock_widget_callbacks()
{
    if (!zsr_widget_lock_active() || zsr_current_user_bypass()) { return; }

    $locked = (array) get_option('zsr_widget_locked', array());
    if (empty($locked)) { return; }

    global $wp_registered_widgets;
    $stub  = ('hidden' === zsr_widget_visitor_action())
        ? 'zsr_widget_stub_hidden'
        : 'zsr_widget_stub_placeholder';

    foreach ((array) $wp_registered_widgets as $widget_id => &$reg) {
        // $widget_id 形如 'zib_widget_ui_main_post-2'，反推 id_base
        $id_base = preg_replace('/-\d+$/', '', $widget_id);
        if (!isset($locked[$id_base]) || in_array($id_base, (array) zsr_widget_exclude(), true)) {
            continue;
        }
        if (is_user_logged_in()) { continue; }
        // dynamic_sidebar() 只调用 $reg['callback']，替换即彻底阻断输出
        $reg['callback'] = $stub;
    }
    unset($reg);
}

function zsr_widget_stub_hidden() { /* 不输出任何内容 */ }
function zsr_widget_stub_placeholder($args, $instance = array())
{
    // 保持与 register_sidebar 的 before_widget 同层结构
    echo '<div class="zib-widget">';
    if (!zsr_widget_hide_title()) { echo '<h3>' . esc_html($instance['title'] ?? '') . '</h3>'; }
    echo '<div class="widget-content">';
    echo zsr_get_login_guide();          // 复用 zib_get_user_singin_page_box()
    echo '</div></div>';
}
```

> **`widgets_init` 优先级 999 的必要性**：主题的 CSF 轨通过 `CSF::register_widgets()`（`setup.class.php:65`）在 `widgets_init` 默认优先级注册，旧轨在默认优先级 10（`widget-index.php:3`）注册。插件必须晚于它们，才能完整覆盖所有小工具的 callback。

### 8.6 前端页面与主题的一致性保障

| 一致性维度 | 保障方式 |
| --- | --- |
| 页面骨架 | 严格复刻 `page.php:35-77` 的 DOM 层级与 class |
| 小工具容器 | 页面支持 `widgets_register` / `widgets_register_container` meta，实现 `page_top_fluid` 等 5 个页面级小工具位置（`page.php:28-32, 40-44, 69-71, 80-84`） |
| 侧边栏 | 复用 `get_sidebar()`，自动继承 `zib_is_show_sidebar()` 与移动端不显示逻辑（`sidebar.php:14-16`） |
| 悬浮按钮 | 移除 `zib_float_right` 与 `zib_footer_tabbar`，与主题投稿页一致（`pages/newposts.php:28-31`） |
| SEO | 主题投稿页用 `add_filter('wp_robots', 'zib_robots_no_robots')`（`pages/newposts.php:74`），插件页面同样禁止索引 |
| 404 处理 | 复用 `get_template_part('template/content-404')` |
| 登录拦截 | 复用 `zib_is_close_sign()`（`inc/functions/zib-user.php:269`） |

### 8.7 兼容性与降级策略

| 依赖缺失 | 降级行为 |
| --- | --- |
| `zib_get_template_page_url()` 不存在 | 走方案 B（`template_redirect` + `load_template()`），在「插件设置」页显示醒目提示 |
| `CSF` 类不存在 | 后台设置页降级为 `register_setting()` + 自绘表单；`zsr_widget_locked` 仍存独立 option，不受影响 |
| `zib_send_json_*` 不存在 | 插件内置同名同协议的兜底实现（`error` 布尔标志，见 §10.2） |
| `zib_newmsg_publish_to_pending()` 不存在 | 仅发`wp_mail`，并在设置页提示站内信不可用 |
| `zib_get_ajax_number_paginate()` 不存在 | 用 `paginate_links()` 替代 |
| 主题大版本升级导致钩子失效 | 激活时做完整依赖自检，列出缺失项并拒绝激活；已在运行的站点进入「兼容模式」，在后台持续告警 |

---

## 9. 样式规范

### 9.1 核心原则

**绝对不引入任何独立前端框架。** 不打包 Bootstrap（主题已有）、Tailwind、Vue、React、jQuery UI、Element 等。插件前端资源仅允许包含**一份极小的自有 CSS**（用于插件特有结构，且优先使用主题 CSS 变量）与**一份可选的 JS**（用于 AJAX 提交与交互增强）。

### 9.2 可用的主题样式资产

| 资产 | 路径 | 加载方式 |
| --- | --- | --- |
| 主样式 | `css/main.css`（含全部 `.but`/`.form-control`/`.theme-box` 等） | 主题已随页面加载，**插件不重复引入** |
| Bootstrap 3 基础 | `css/bootstrap.css` | 主题已加载 |
| FontAwesome 4 | — | 主题强制 FA4（`inc/options/options.php:28`挂 `csf_fa4`），图标须用 FA4 类名，**不可用 FA5+** |
| 日夜主题 | CSS 变量 | 见§9.4 |

### 9.3 类名复用速查（实测出处）

**容器 / 卡片**

| class | 用途 | 出处 |
| --- | --- | --- |
| `.article.main-bg.theme-box.box-body.radius8.main-shadow` | 页面主内容卡片 | `page.php:61` |
| `.theme-box` / `.mb20` | 通用容器 / 下边距 20px | `css/main.css:828-831` |
| `.box-bg` | 主背景 + 阴影 | `css/main.css:833` |
| `.muted-box` | 次级容器 | `css/main.css:846` |
| `.border-box` | 描边容器 | `css/main.css:852` |
| `.zib-widget` | 小工具容器（sidebar 强制施加） | `css/main.css:838` + `inc/widgets/widget-index.php:181` |
| `.zib-widget-wrap` | CSF 小工具最外层 | `css/main.css:3792` |
| `.widget-content` | 小工具内容容器 | `widget-options.class.php:655` |
| `.fluid-widget-wrap` | 全宽度小工具带 | `page.php:41` |
| `.content-wrap` / `.content-layout` | 内容区布局 | `page.php:36-37` |
| `.main-bg` / `.main-shadow` | 主背景 / 主阴影 | `css/main.css:229` |

**按钮**

| class | 用途 | 出处 |
| --- | --- | --- |
| `.but` | **主题主按钮**（不是 `.btn`） | `css/main.css:1665` |
| `.but.cir` | 圆形按钮 | `css/main.css:1776` |
| `.but-group` | 按钮组（自动处理间距圆角） | `css/main.css:1797` |
| `.but-average` | 按钮均分 | `css/main.css:1813` |
| `.but.hollow` | 镂空按钮 | `css/main.css:1715` |
| `.btn-block` | 块级按钮 | — |
| `.c-red` | 语义红色 | — |
| `.wp-ajax-submit` | **主题 AJAX 提交按钮标记** | `action/new_posts.php:48` |

**表单 / 列表 / 状态**

| class | 用途 | 出处 |
| --- | --- | --- |
| `.form-control` / `.form-group` | 表单控件 / 分组 | `css/main.css:2606` / `bootstrap.css:2352` |
| `.checkbox` / `.radio` / `.checkbox-inline` | 勾选与单选 | `bootstrap.css:2356-2357, 2382-2383` |
| `.posts-mini-lists` / `.posts-mini` / `.posts-mini-con` | 列表容器 / 条目 / 内容区 | `inc/widgets/widget-posts.php:1271, 1230` |
| `.flex` / `.flex1` / `.jsb` / `.xx` | 布局（两端对齐等） | 主题工具类 |
| `.title-theme` | 区块标题 | `inc/widgets/widget-index.php:397` |
| `.index-tab` / `.tab-content` / `.tab-pane` | Tab 布局 | — |
| `.muted-color` / `.muted-2-color` / `.muted-3-color` | 三级弱化文字 | 主题工具类 |
| `.em09` | 字号 0.9em | — |
| `.text-center` / `.pull-right` / `.ml10` / `.mr10` | 对齐与间距 | — |
| `.badge` / `.avatar-badge` | 状态徽章 | `css/main.css` |
| `.theme-pagination` / `.ajax-pag` / `.next-page` | 分页 | 主题分页函数输出 |
| `.placeholder` + `.k1`/`.t1`/`.s1` | 骨架屏 | `css/main.css:234-240` |
| `.hidden-xs` / `.visible-xs-block` | 响应式显隐 | `css/main.css` |
| `.obs-animate` + `.ani-*` | 滚动入场动画 | `widget-options.class.php:573-587` |

> **注意**：主题自身有一个拼写为 `.badg`（无 `dge`）的 class（`widget-options.class.php:649`），但**`css/main.css` 中未找到 `.badg` 定义**，该class 实际无样式，仅靠内联背景色与 `.c-red` 呈现。**插件不要使用 `.badg`**，请用标准 `.badge`。

### 9.4 自有CSS 的严格约束

插件唯一允许的自有样式文件 `assets/css/zsr-frontend.css`，硬性约束：

1. **选择器全部以 `.zsr-` 前缀开头**，禁止出现任何无前缀的裸标签或类选择器，避免污染全局。
2. **只允许使用主题已定义的 CSS 变量**，禁止硬编码颜色（写死 `#fff`、`rgb(...)`）以保证日夜模式自动适配。
3. 体积上限 **≤ 8KB**（未压缩）。
4. 仅在插件页面按需加载：`add_action('template_redirect', 'zsr_enqueue_assets', 6)` 中判断 `zsr_is_our_page()` 为真才 `wp_enqueue_style()`。
5. 必须支持主题的日夜切换（跟随 `html` 上的主题 class 与 CSS 变量）。

```css
/* 合规示例 */
.zsr-status-badge { border-radius: 4px; padding: 2px 8px; font-size: .82em; }
.zsr-status-badge.is-pending    { background: rgba(255,170,0,.12);  color: #d98b00; }
.zsr-status-badge.is-rejected   { background: rgba(255,0,70,.10);   color: #e0466a; }
.zsr-status-badge.is-approved   { background: rgba(0,170,90,.12);   color: #1a9850; }
.zsr-review-panel  { border-left: 3px solid var(--main-color, #ff0071); padding-left: 12px; }
@media (prefers-color-scheme: dark) {
    /* 若主题用 class 切换而非media 查询，则改为 .dark .zsr-xxx */
}
```

### 9.5 JS 约束

| 项 | 要求 |
| --- | --- |
| 库| 复用主题已加载的 `jquery`（主题 AJAX 提交依赖 jQuery 与 `wp-ajax-submit` 机制，见 `action/new_posts.php:48`） |
| 模块形式 | 标准 `wp_enqueue_script`，句柄 `zsr-frontend`，`deps = ['jquery']` |
| 提交方式 | 优先复用主题的 `.wp-ajax-submit` class（主题前端 JS 已绑定该class 走统一 AJAX 通道），零自定义提交逻辑 |
| 文件大小 | ≤ 12KB（未压缩） |
| 禁用 | 禁止内联 `<script>`、禁止 `eval`、禁止 `document.write`、禁止引入外部 CDN |
| 局部刷新 | 复用 `zib_get_ias_ajaxpager()` 的既有分页机制，不自研无限滚动 |

### 9.6 文案与国际化

| 项 | 要求 |
| --- | --- |
| 插件翻译域 | `zib-sub-review`（**禁止**使用 `zib_language`，那是主题域） |
| 加载时机 | `after_setup_theme` 优先级 30（确保主题文本域已加载） |
| PHP 字符串 | 全部包裹 `__()` / `esc_html__()` / `esc_attr__()`，域为 `zib-sub-review` |
| 中文文案风格 | 与主题一致：简洁、无表情、动词开头。参考主题既有文案「内容已提交，正在等待审核」（`action/new_posts.php:263`）、「已驳回此内容，并已通知作者」（`inc/functions/bbs/action/ajax-posts.php:504`） |
| 状态文案可配 | 状态徽章文案写入设置项，支持站长自定义措辞（如「已驳回」改为「需修改」） |

---

## 10. 安全与性能要求

### 10.1 安全要求

**10.1.1 权限校验（三层，缺一不可）**

| 层 | 措施 | 依据 |
| --- | --- | --- |
| 页面层 | 模板文件首行 `if (!defined('ABSPATH')) { exit; }` + 权限前置校验，无权则渲染 404 | WordPress 通用规范 |
| 查询层 | `pre_get_posts` 注入 `author` / `post_status` / `meta_query` 条件；**不依赖前端隐藏** | 防御纵深 |
| 动作层 | 每个 AJAX 回调独立校验能力键 | 见 §6.4 |

**10.1.2 能力键检查顺序（严格照此顺序，缺一即被攻破）**

```
① zib_ajax_verify_nonce()                    // 先验签名，再做任何数据读取
② 参数类型与范围校验                          // post_id 强制 (int)，msg 强制 trim
③ zib_current_user_can('zsr_review')         // 再验权限
④ post = get_post($id)；empty($post->ID) → 退出
⑤ post_type === 'post'                       // 防止越权审核文章以外的类型（如 page）
⑥ post_status === 'pending'                  // 状态前置条件
⑦ post_author == 当前用户 && !允许自审 → 拒绝
⑧ method 在白名单内
⑨ 意见长度/必填校验
⑩ 执行业务
```

> 第⑤ 步极为重要：`wp_update_post()` 可修改任意文章类型，**必须显式校验 `post_type === 'post'`**，否则审核接口可被用于篡改页面（page）、导航菜单项（nav_menu_item）等。这是真实可利用的越权点。

**10.1.3 Nonce 规范**

| 场景 | nonce action | 字段名 | 生成方式 |
| --- | --- | --- | --- |
| 提交稿件 | `zsr_submit` | `_wpnonce` | `wp_nonce_field('zsr_submit', '_wpnonce', false, false)` |
| 更新稿件 | `zsr_update` | `_wpnonce` | 同上 |
| 审核动作 | `zsr_review` | `_wpnonce` | 同上 |
| 小工具配置 | `zsr_widget_save` | `_wpnonce` | 同上 |

**遵循主题范式**：nonce action 与 AJAX `action` 名**同名**，`zib_ajax_verify_nonce()` 不传参时自动取 `$_REQUEST['action']`（`action/function.php:280-291`），因此直接无参调用即可。

```php
// action/function.php:280-291（节选）
function zib_ajax_wp_verify_nonce($action = null, $name = '_wpnonce')
{
    if (!$action) {
        $action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : -1;
    }
    if (!isset($_REQUEST[$name]) || !wp_verify_nonce($_REQUEST[$name], $action)) {
        zib_send_json_error(__('环境异常，请刷新页面后稍候再试', 'zib_language'), 'warning');
    }
}
```

**10.1.4 输入输出净化清单**

| 输入 | 位置 | 净化措施 |
| --- | --- | --- |
| `post_id` | `$_REQUEST` | `(int)` 强制转换 + `get_post()` 存在性校验 |
| `view` | `$_GET` | `sanitize_key()` + `in_array($v, $whitelist, true)` 白名单，**严禁拼接路径** |
| `method` | `$_REQUEST` | 白名单校验 |
| `msg`（审核意见） | `$_REQUEST` | `trim()` + `wp_strip_all_tags()` + `mb_substr(0, zsr_reason_maxlength)`；输出时 `esc_html()` |
| 稿件标题/正文 | `$_POST` | 走 `wp_insert_post()` / `wp_update_post()`，由 WP 自身 `sanitize_post` 处理（`kses` 依站点设置） |
| 分类 | `$_POST` | `array_map('absint', ...)` |
| 标签 | `$_POST` | `sanitize_text_field()` |

**输出转义清单**：`esc_html()` 用于所有文本节点、`esc_attr()` 用于所有属性、`esc_url()` 用于所有链接、`wp_kses_post()` 用于富文本。

**10.1.5 并发与竞态**

两个审核人同时审核同一稿件会产生覆盖。采用 transient 锁：

```php
// 获取锁
$lock = get_transient('zsr_lock_' . $post_id);
if ($lock && $lock != get_current_user_id() && (time() - $lock) < 60) {
    zib_send_json_error('该稿件正被其他审核人处理，请稍候再试');
}
set_transient('zsr_lock_' . $post_id, get_current_user_id(), 60);

// 更新后立即释放
delete_transient('zsr_lock_' . $post_id);
```

> transient 未过期时 `get_transient()` 返回过期的 ID，`(time() - $lock) < 60` 判据使锁在60 秒后自动失效，避免异常导致死锁。

**10.1.6 CSRF / XSS / SQLi 专项**

| 威胁 | 防护 |
| --- | --- |
| CSRF | 全部状态变更走 `wp_ajax_*` + nonce 校验 |
| 存储型 XSS | 审核意见 `wp_strip_all_tags()` 入库 + `esc_html()` 出库；稿件正文依赖 WP 的 `kses` 过滤 |
| 反射型 XSS | 所有 `$_GET` 参数输出前转义；`view` 白名单，杜绝反射 |
| SQL 注入 | 全部查询走 `$wpdb->prepare()` 或 `WP_Query` / `get_post_meta` 参数化 API，**禁止拼接 SQL** |
| 权限提升 | 能力键只能写入 `user_cap`，不调用 `add_cap()`，不创建 WP 角色 |
| 开放重定向 | 所有跳转用 `wp_safe_redirect()`，不用 `wp_redirect()` |
| 敏感信息泄漏 | 权限判定错误信息统一返回「权限不足」，不透露对象存在性（防枚举）；404 而非 403 用于详情页越权访问 |

### 10.2 AJAX 响应协议

**必须与主题一致**（实测 `action/ajax.php:14-65`）：

```php
// 成功：error = false
zib_send_json_success(array(
    'msg'        => '已通过',
    'reload'     => true,
    'hide_modal' => true,
    'goto'       => admin_url('edit.php'),
));

// 失败：error = true，ys 决定前端提示样式
zib_send_json_error('您没有审核此稿件的权限');
```

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `error` | `bool` | **`false` = 成功**（注意：与 WP 标准的 `success` 相反） |
| `ys` | `string` | 仅错误响应有，`danger`/`warning`/`info`/`success` |
| `msg` | `string` | 提示文案（传字符串时落到此键） |
| `reload` | `bool` | 前端是否需刷新 |
| `hide_modal` | `bool` | 是否关闭弹窗 |
| `goto` | `string` | 跳转地址 |
| `post_id` | `int` | 稿件 ID |

**降级实现**（`zib_send_json_*` 不存在时使用）：

```php
function zsr_send_json_error($data = false, $type = 'danger') {
    $send = array('error' => true, 'ys' => $type);
    if (is_array($data))      { $send = array_merge($data, $send); }
    elseif (is_string($data)) { $send['msg'] = $data; }
    wp_send_json($send);
}
function zsr_send_json_success($data = false, $type = '') {
    $send = array('error' => false);
    if ($type) { $send['type'] = $type; }
    if (is_array($data))      { $send = array_merge($send, $data); }
    elseif (is_string($data)) { $send['msg'] = $data; }
    wp_send_json($send);
}
```

> 两个函数内部均自带 `exit`/`wp_send_json`（含 exit），调用后**不得**再执行任何代码。

### 10.3 性能要求

**10.3.1 加载策略**

| 场景 | 策略 |
| --- | --- |
| 后台设置页 | 仅 `is_admin()` 时加载插件全部代码；前台请求**不加载**任何插件文件（除必须的常量定义） |
| 前台资源 | 仅在 `zsr_is_our_page()` 为真时 enqueue，权重 0（关键CSS 除外） |
| 审核台列表 | `no_found_rows => true` 避免 `found_posts` 的额外查询；总数用独立 `SQL_CALC_FOUND_ROWS` 或限制深度分页 |
| 小工具枚举 | 仅在后台设置页按需调用；前台渲染**不**枚举，只做 `isset($locked[$id_base])` 的 O(1) 判断 |
| 对象缓存 | 待审数量缓存于 `wp_cache`（group `zsr`，TTL 60s），在审核状态变更时主动 `wp_cache_delete()` |
| 选项读取 | `zsr_widget_locked` 单次 `get_option()` 后存静态变量，避免同一请求内重复读 |
| 查询参数缓存 | 遵循主题惯例，禁止缓存 wp_query |

**10.3.2 性能预算**

| 指标 | 预算 | 测量方法 |
| --- | --- | --- |
| 非投稿页的前台 TTFB 增量 | **0ms**（不加载任何插件资源） | 开启资源加载前后对比 |
| 投稿页首屏加载体积增量 | ≤ 20KB（CSS 8KB + JS 12KB） | 浏览器 Network 面板 |
| 投稿页服务端耗时增量 | ≤ 30ms | `wp_start_timer` 或 P3 Profiler |
| 审核台首屏 DB 查询数增量 | ≤ 8 次 | `SAVEQUERIES` 常量 |
| 小工具锁定判断耗时 | ≤ 0.01ms/小工具 | 基准测试 |
| 提交/审核动作耗时 | ≤ 200ms（不含邮件） | 埋点统计 |

**10.3.3 数据库层面约束**

| 约束 | 说明 |
| --- | --- |
| 不新增数据库表 | 完全使用 WordPress 的 `wp_options` / `wp_postmeta` |
| 索引 | 依赖 WP 内置的 `wp_postmeta.post_id`（`meta_key` 无索引，故 meta 查询必须 `LIMIT`） |
| meta 查询上限 | 「审核记录」限制 50 条；审核队列分页最大 50 页，超出引导使用 WP 后台 |
| 无 N+1 查询 | 列表渲染前用 `update_meta_cache('post', $ids)` 与 `update_post_author_cache()` 一次性预热 |
| 事务一致性 | 审核为「改 post_status + 写 meta」两步；第一步成功第二步失败时记录 error_log 并回滚 post_status，避免状态与记录不一致 |

**10.3.4 定时任务（可选）**

若启用「过期稿件提醒」扩展功能，用 `wp_schedule_event()` 注册每日任务，且必须在 `uninstall.php` 中 `wp_clear_scheduled_hook()`。**v1 不实现此功能**（见 N2）。

---

## 11. 边界情况与验收标准

### 11.1 边界情况清单

| 编号 | 场景 | 期望行为 | 优先级 |
| --- | --- | --- | --- |
| EC-01 | 未登录用户直接访问投稿页 URL | 渲染登录/注册引导（复用 `zib_get_user_singin_page_box()`），不输出任何表单，也**不返回 404**（避免暴露功能存在性）；若站点关闭注册（`zib_is_close_sign()`），提示联系管理员 | P0 |
| EC-02 | 已登录用户访问投稿页但无 `zsr_submit` 权限 | 显示「权限不足」提示，隐藏提交表单；仍可浏览自己的历史稿件（若有） | P0 |
| EC-03 | 用户提交被限频拦截（主题 `zib_brush_limit_post`） | 透传主题错误文案，不重复提示 | P1 |
| EC-04 | 提交时站点处于「关闭注册」状态 | 阻止投稿并提示 | P1 |
| EC-05 | 审核人操作他人稿件但无 `zsr_review_others` | 返回「权限不足」，不泄漏稿件标题等信息 | P0 |
| EC-06 | 审核人操作自己的稿件且 `zsr_allow_self_review=false` | 隐藏操作区；若伪造请求则服务端拒绝 | P0 |
| EC-07 | 稿件已被他人处理（状态已非 `pending`），审核人基于旧页面再次点击 | 返回「该稿件不处于待审核状态，请刷新后重试」，不覆盖他人结果 | P0 |
| EC-08 | 驳回时未填原因且 `zsr_reject_reason_required=true` | 前端拦截 + 服务端二次校验（前端校验可被绕过），返回明确提示 | P0 |
| EC-09 | 审核意见超过字数上限 | 前端 `maxlength` 限制 + 服务端 `mb_substr` 截断，**不报错** | P1 |
| EC-10 | 两名审核人同时审核同一稿件 | 后提交者收到「正被其他审核人处理」提示；或以最后写入为准但保留 `zsr_review_history` 两条记录 | P0 |
| EC-11 | 站点运营误点主题「重置设置」（`zibll_options` 被重置） | `zsr_widget_locked` 与 `zsr_options` **不受影响**（独立 option）；仅 `zibll_options['user_cap']` 中的 `zsr_*` 键丢失 → 下次 `admin_init` 自动补注册（`isset` 幂等），权限恢复默认 | P0 |
| EC-12 | 作者在稿件被驳回后再次编辑并重新提交 | `post_status` 保持 `pending`，`zsr_state` 重置为 `pending`，`zsr_submit_count+1`，历史记录保留 | P1 |
| EC-13 | 稿件进入回收站（`trash`） | 审核入口对 `trash` 稿件显示「已删除」且禁用操作按钮 | P1 |
| EC-14 | 投稿人随后注销账号 | 稿件保留（属站点内容）；`zsr_reviewed_by` 指向的用户 ID 失效 → 用 `zsr_reviewer_name` 快照显示 | P2 |
| EC-15 | 审核人随后注销账号 | 同上，快照机制保证审核记录可读 | P2 |
| EC-16 | 主题关闭了 `post_article_s`（文章功能总开关，`_pz('post_article_s')`） | 投稿视图整体隐藏并提示「站点已关闭文章功能」，与 `pages/newposts.php:36-40` 行为一致 | P1 |
| EC-17 | 小工具被锁定但访客已登录 | 正常显示，**不做任何判断开销短路** | P0 |
| EC-18 | 小工具被锁定，管理员访问 | `zsr_widget_admin_bypass=true` 时始终可见，便于排查 | P1 |
| EC-19 | 被锁定的小工具属于 `widget_ui_search` / `widget_ui_user` | 强制排除（`zsr_widget_exclude` 兜底），避免搜索框/用户卡片锁死导致前台不可用 | P0 |
| EC-20 | 小工具在主题中已注销（如 `unregister_d_widget()` 注销的 7 个 WP 默认小工具） | 枚举时跳过，不出现在后台列表 | P2 |
| EC-21 | 同名 id_base 在两轨重名（`widget_ui_notice`） | 实际只有 CSF 轨注册成功，按 id_base 匹配不产生歧义；已在代码注释中标注 | P2 |
| EC-22 | 用户安装插件时未安装 Zibll 主题 | 拒绝激活，后台显示「检测到当前未使用 Zibll 主题，插件无法运行」，提供切换主题引导，**不白屏** | P0 |
| EC-23 | 主题从 V9.0 降级到 V8.x（关键函数消失） | 已运行的站点进入兼容模式：前台页面显示维护提示 + 后台持续告警列出缺失函数；已产生的 pending 稿件**不受影响**（本质是 WP 文章） | P1 |
| EC-24 | 插件目录被直接删除（未走卸载流程） | `zsr_widget_locked` 等 option 成为孤儿数据；因前台判定严格依赖该 option，锁定会**持续生效**。缓解：所有前台判定加 `zsr_widget_enable` 开关与「插件目录存在性」检查，检测不到插件文件时自动失效并记录日志 | P1 |
| EC-25 | 审核台队列稿件数超过 5000 | 分页上限 50 页；超出后提示「请前往 WordPress 后台批量处理」，避免慢查询拖垮页面 | P1 |
| EC-26 | `post_title` 为空或仅含空格 | 前端 `required` + 服务端 `mb_strlen(trim())` 校验 | P1 |
| EC-27 | 审核意见含 HTML/JS | 入库前 `wp_strip_all_tags()`，输出前 `esc_html()`，双重防护 | P0 |
| EC-28 | 恶意用户构造 `view=../../wp-config` | 白名单校验直接回落默认视图，无文件包含风险 | P0 |
| EC-29 | 一篇稿件被多个前台审核台用户同时打开详情 | 各自可审核，第二次提交时按 EC-10 处理 | P1 |
| EC-30 | 稿件的分类被删除 | 详情页显示「分类已删除」占位，不报错 | P2 |

### 11.2 验收标准 · 模块 A（投稿审核）

| 编号 | 验收项 | 通过条件 |
| --- | --- | --- |
| AC-01 | 独立页面 | 插件激活后自动创建 WP 页面并可访问；**页面由页面模板实现，全站短代码数量为 0** |
| AC-02 | 非短代码验证 | 检索插件全部文件，`add_shortcode` 调用数为 0；主题短代码注册表未新增 |
| AC-03 | 菜单入口 | 后台配置开启后，主题菜单出现「我的投稿」入口，点击跳转正确 |
| AC-04 | 投稿提交 | 具备 `zsr_submit` 权限的用户提交稿件，落库 `post_status = pending`、`post_type = post`、`post_author` 正确、3 个 meta 齐全 |
| AC-05 | 主题一致性 | 投稿页 DOM 层级与 `page.php` 一致；按钮使用 `.but`，表单使用 `.form-control`，未出现 `.btn` |
| AC-06 | 状态浏览 | 作者可见自己的稿件及当前状态徽章；他人稿件不可见（直链访问被拒） |
| AC-07 | 编辑权限 | 作者可编辑自己的 `draft`/`pending`/`rejected` 稿件；编辑他人稿件返回 403 |
| AC-08 | 审核台可见性 | 有 `zsr_review` 权限的用户可见审核台入口；无权限者菜单不显示、直链访问返回 404 |
| AC-09 | **通过** | 审核人点击通过 → `post_status` 变为 `publish`（可配置），`zsr_state = approved`，写入 3 个审核 meta，历史记录新增 1 条 |
| AC-10 | **驳回** | 审核人填写驳回原因后提交 → 状态按配置流转，`zsr_reject_reason` 正确保存，作者收到站内信 + 邮件（双通道可单独关闭） |
| AC-11 | **退回** | 审核人退回 → `post_status = draft`（可配置），作者可立即编辑并重新提交 |
| AC-12 | 驳回原因必填 | 开启必填后，空原因提交被**服务端**拒绝（绕过前端直接发请求同样被拒） |
| AC-13 | 权限组配置 | 后台把 `zsr_review` 仅配置给「认证用户」后，非认证用户即使原为版主也无法访问审核台；改回后恢复 |
| AC-14 | 动作开关 | 关闭「退回」后，审核台不显示退回按钮，且伪造 `method=return` 的请求被服务端拒绝 |
| AC-15 | 状态机完整性 | 按 §6.5 状态机逐条验证，所有合法迁移成功，所有非法迁移被拒 |
| AC-16 | 通知开关 | 关闭站内信通道后，驳回仅发邮件；关闭全部通知后两者均不发 |
| AC-17 | 审核记录 | 审核人在「审核记录」中看到自己处理过的全部稿件，含时间、动作、意见 |
| AC-18 | 并发安全 | 两个浏览器同时对同一 `pending` 稿件执行审核，后者收到明确提示，两条操作均可追溯 |

### 11.3 验收标准 · 模块 B（小工具控制）

| 编号 | 验收项 | 通过条件 |
| --- | --- | --- |
| AC-19 | 枚举完整性 | 后台列表展示全部**已启用**小工具（含CSF 轨 42 个、旧轨 9 个、商城/社区扩展 9 个中的已启用部分），每项含显示名、id_base、所在侧边栏、实例数 |
| AC-20 | 已注册 vs 已启用 | 注册但未放入侧边栏的小工具不出现在「已启用」分组（可放入「已注册未启用」折叠区） |
| AC-21 | 逐项配置 | 可对单个 id_base 设置「仅登录后可见」，保存后立即生效（不需刷新缓存） |
| AC-22 | **独立存储** | 配置存于独立 option `zsr_widget_locked`；执行「重置主题设置」后锁定关系**依然生效** |
| AC-23 | CSF 轨生效 | 锁定 `zib_widget_ui_main_post` 后，访客访问首页时该模块**完全不输出**（检查 HTML 源码中无该模块特征字符串），登录后正常显示 |
| AC-24 | 旧轨生效 | 锁定 `widget_ui_search` 之外的旧轨小工具（如 `widget_ui_mini_posts`）后同样生效（验证 callback 替换路径） |
| AC-25 | 访客行为三态 | `placeholder` / `hidden` / `upgrade` 三种配置分别产出登录引导 / 无输出 / 升级引导 |
| AC-26 | 管理员旁路 | `zsr_widget_admin_bypass=true` 时，管理员在未登录预览模式下也能看到全部小工具 |
| AC-27 | 排除名单 | `widget_ui_search` 等被排除的小工具即使在锁定列表中也不生效 |
| AC-28 | 多实例一致性 | 同id_base 的多个实例（如 `zib_widget_ui_main_post-2` 与 `-3`）在访客视角下**全部**被隐藏 |
| AC-29 | 无性能损耗 | 未启用该功能（默认关闭）时，前台小工具渲染耗时与未装插件前**无差异**（误差 < 1%） |
| AC-30 | 结构不破坏 | 占位输出与隐藏输出下，页面 DOM 结构合法（无未闭合标签），通过 HTML 校验 |

### 11.4 验收标准 · 通用质量

| 编号 | 验收项 | 通过条件 |
| --- | --- | --- |
| AQ-01 | 主题零修改 | `git diff` 显示 `E:\只比主题\zibll` 目录**无任何变更**；插件未使用主题 `func.php` |
| AQ-02 | 主题可升级 | 模拟主题从 V9.0 升级到下一版本，插件功能不失效、无 PHP 致命错误 |
| AQ-03 | 无独立前端框架 | 插件目录中无 `bootstrap*.css`、`tailwind*.css`、`vue*.js`、`react*.js`、`jquery-ui*`；自有 CSS ≤ 8KB、JS ≤ 12KB |
| AQ-04 | 样式全部复用 | 插件自有 CSS 中无硬编码颜色，全部使用主题类或 CSS 变量；页面视觉与主题投稿页一致 |
| AQ-05 | 无PHP 告警 | 在 `WP_DEBUG = true` 且 `error_log` 完整开启的环境下，浏览前台 20 个页面 + 后台全部设置页 + 执行 10 次提交与 30 次审核，`error_log` 中**无**来自插件文件的 notice/warning/deprecated |
| AQ-06 | 文本域隔离 | 全项目检索 `zib_language`，插件文件中出现次数为 0（仅允许出现在「说明需复用主题函数」的注释中） |
| AQ-07 | 翻译就绪 | 生成的 `.pot` 包含全部前台与后台文案 |
| AQ-08 | 卸载干净 | 启用 `WP_UNINSTALL_PLUGIN` 执行卸载 → `zsr_*` 全部 option 被删除，`zibll_options['user_cap']` 中仅 `zsr_*` 键被移除（主题其他键与投稿页 ID 不受影响），post meta 保留 |
| AQ-09 | 性能预算 | §10.3.2 全部指标达标 |
| AQ-10 | 安全扫描 | 通过 WordPress Theme Check 插件核心检查项；无 `eval`、无未转义输出、无未 nonce 保护的状态变更 |
| AQ-11 | 依赖自检 | 手动删除某主题函数（测试环境）后，插件激活时给出明确缺失清单；运行中缺失则进入兼容模式并告警 |
| AQ-12 | 文档一致 | 本文档所列全部文件路径、函数名、钩子名、CSS 类名与主题源码实际一致（开发完成后逐条比对） |

### 11.5 测试用例矩阵

| 环境 | 配置 | 用例重点 |
| --- | --- | --- |
| PHP 7.0 + WP 5.0 | 最低版本 | 基础功能可用，无语法错误 |
| PHP 8.2 + WP 6.5 | 常规生产 | 全功能回归 |
| PHP 8.5 + WP 6.8 | 主题声明上限 | 兼容性（`style.css` 声明 `Requires PHP: 7.0-8.5`） |
| Zibll V8.1 | 最低主题版本 | 依赖函数存在性自检 |
| Zibll V9.0 | 开发基准版本 | 全功能 |
| 多人社区站| 版主 + 分区版主 + 超级版主并存 | AC-13 权限组配置、EC-10 并发 |
| 含商城/社区模块 | 开启 `shop_s` / `bbs_s` | 小工具枚举完整性（AC-19） |
| 日夜模式切换 | 主题暗色 | 自有 CSS 适配（AQ-04） |
| 站点有大量pending 稿件 | 注入 5000篇 | 审核台分页性能（EC-25） |
| 用户名含特殊字符 | `<script>`、中文 emoji | XSS 防护（EC-27） |

---

## 12. 附录

### 12.1 插件目录结构

```
zibll-submission-review/                插件根目录
├── zibll-submission-review.php          主入口：常量定义、依赖检测、加载
├── uninstall.php卸载清理
├── readme.txt                          WP 标准 readme
│
├── inc/
│   ├── core/
│   │   ├── dependencies.php             主题依赖检测（_pz/zib_current_user_can/CSF…）
│   │   ├── capabilities.php             能力键注册与 is_can_roles 扩展
│   │   └── helpers.php                  工具函数（状态映射、清洗、通知）
│   │
│   ├── admin/
│   │   ├── options.php                  CSF 设置页注册（CSF::createOptions）
│   │   ├── fields.php                   5 个 section 的字段定义
│   │   ├── widget-panel.php             小工具控制列表 UI（可复用 CSF multicheck）
│   │   └── notices.php                  兼容模式告警、未检测到主题提示
│   │
│   ├── frontend/
│   │   ├── page-router.php              自动建页 + 路由 + 模板分发
│   │   ├── view-submit.php              提交/编辑表单
│   │   ├── view-my.php                  我的投稿列表
│   │   ├── view-review.php              审核台
│   │   ├── view-history.php             审核记录
│   │   └── query.php                    WP_Query 封装（含权限注入）
│   │
│   ├── ajax/
│   │   ├── submit.php                   action=zsr_submit / zsr_update
│   │   ├── review.php                   action=zsr_review
│   │   └── count.php                    action=zsr_pend_count（徽章计数）
│   │
│   └── widget/
│       ├── enumerator.php               枚举已注册/已启用小工具
│       ├── locker.php                   锁定逻辑（CSF 过滤器 + callback 替换）
│       └── sync.php                     zsr_options ↔ zsr_widget_locked 同步
│
├── templates/
│   ├── zsr-submissions.php              页面模板（Template Name: 子比-投稿与审核）
│   ├── virtual-page.php                 方案 B 兜底模板
│   └── parts/
│       ├── tab-bar.php                  视图切换 Tab
│       ├── form.php                     投稿表单
│       ├── list-item.php                列表条目
│       ├── status-badge.php             状态徽章
│       ├── review-panel.php             审核操作区
│       └── review-log.php               审核历史时间线
│
├── assets/
│   ├── css/zsr-frontend.css             前端样式（≤ 8KB，.zsr- 前缀）
│   ├── css/zsr-admin.css                后台样式（≤ 4KB）
│   └── js/zsr-frontend.js               前端脚本（≤ 12KB，deps: jquery）
│
└── languages/
    └── zibll-submission-review.pot      翻译模板
```

### 12.2 关键主题代码位置索引

| 功能 | 文件:行号 |
| --- | --- |
| 主题入口与加载顺序 | `functions.php:15` → `inc/inc.php:84-102` |
| `zib_require_end` 钩子 | `inc/inc.php:102` |
| `_pz()` / `_spz()` 设置读写 | `inc/dependent.php:195-228` |
| `zib_get_template_page_url()` | `inc/dependent.php:105-167` |
| `zib_get_option()`（**不要用于读主题设置**） | `inc/dependent.php:271-291` |
| `zib_get_option_meta_keys()` 聚合重定向 | `inc/dependent.php:235-269` |
| 权限判定 `zib_user_can()` | `inc/functions/user/user-cap.php:21-214` |
| `zib_current_user_can()` | `inc/functions/user/user-cap.php:227` |
| `zib_is_can_roles()` 身份匹配 | `inc/functions/user/user-cap.php:233-267` |
| 权限扩展过滤器 | `user-cap.php:213`（`zib_user_can`）、`:263`（`is_can_roles`） |
| 8 类身份定义 | `inc/options/options-module.php:1356-1438` |
| 权限矩阵字段工厂 | `inc/options/options-module.php:1677-1750` |
| 投稿 capability 定义 | `inc/options/options-module.php:1478-1570` |
| 用户等级 / VIP / 认证 / 封禁 | `inc/functions/user/user-level.php:199`、`zibpay/functions/zibpay-vip.php:853`、`user-auth.php:15`、`user-ban.php:19` |
| AJAX 返回封装 | `action/ajax.php:14-37`（error）、`:39-65`（success） |
| nonce 校验封装 | `action/function.php:280-291`、`:721-725` |
| 投稿保存与pending 判定 | `action/new_posts.php:180-221`、`:258-266` |
| 投稿 AJAX 注册 | `action/new_posts.php:284-287` |
| `zib_pre_insert_post` 限频拦截 | `inc/functions/zib-theme.php:896`（钩子）、`action/new_posts.php:218`（触发） |
| 论坛审核 AJAX（范式参考） | `inc/functions/bbs/action/ajax-posts.php:484-541` |
| 评论审核 AJAX（范式参考） | `action/comment.php:20-68` |
| 驳回通知函数 | `inc/functions/message/functions/new.php:317-347` |
| 稿件页通知邮件 | `inc/functions/message/functions/new.php:164` |
| CSF 设置页注册 | `inc/options/admin-options.php:16-42` |
| `csf_zibll_options_sections` 过滤器 | `inc/csf-framework/classes/admin-options.class.php:98` |
| CSF `saved` 钩子 | `inc/csf-framework/classes/admin-options.class.php:392` |
| CSF 覆盖层配置 | `inc/options/options.php:166-171` |
| `CSF_Widget` 基类 | `inc/csf-framework/classes/widget-options.class.php:22` |
| `CSF_Widget::widget()` 渲染 | `widget-options.class.php:631-687` |
| `CSF_Widget::is_show()` + 过滤器 | `widget-options.class.php:518-528` |
| `CSF_Widget::wrap_attributes()` | `widget-options.class.php:456-515` |
| `CSF::createWidget()` | `inc/codestar-framework/classes/setup.class.php:289-293` |
| `CSF::register_widgets()` | `inc/codestar-framework/classes/setup.class.php:69-86` |
| 小工具注册（旧轨） | `inc/widgets/widget-more.php:3-10`、`widget-posts.php:3-10`、`widget-slider.php:3-10` |
| CSF 轨小工具注册 | `inc/widgets/widget-user.php:221`、`widget-more.php:1712` |
| 侧边栏注册 | `inc/widgets/widget-index.php:60-154`（主）、`:156-171`（优先级 99） |
| `zib_register_sidebar()` | `inc/widgets/widget-index.php:173-188` |
| 页面级动态侧边栏 | `inc/widgets/widget-index.php:191-274` |
| 前台投稿侧边栏 | `inc/widgets/widget-index.php:140-149`（`newposts_sidebar_top` / `_bottom`） |
| `dynamic_sidebar_params` 过滤器 | `inc/widgets/widget-class.php:67-79` |
| 小工具通用 AJAX 入口 | `inc/widgets/widget-index.php:283-299` |
| 已注册 id_base 枚举 | `inc/widgets/widget-import.php:59-73` |
| 已启用小工具 + 配置读取 | `inc/widgets/widget-import.php:81-136` |
| widget id 解析 | `inc/widgets/widget-import.php:15-33` |
| WP 默认小工具注销 | `inc/widgets/widget-index.php:23-33` |
| 投稿页模板（结构参考） | `pages/newposts.php:1-80` |
| 页面模板骨架（DOM 参考） | `page.php:35-84` |
| 独立页 `template_redirect` 范式 | `inc/functions/zib-theme.php:2020-2030` |
| 用户中心模板加载 | `inc/functions/user/user.php:110`（优先级 5） |
| 侧边栏渲染与移动端屏蔽 | `sidebar.php:14-45` |
| AJAX 分页容器 | `inc/functions/functions.php:2360-2369` |
| 数字分页 / 无限滚动 | `inc/functions/functions.php:2212` / `:2458` |
| 空状态 / 骨架屏 | `inc/functions/functions.php:2421` / `inc/widgets/widget-posts.php:1245` |
| 登录引导 / 用户卡片 | `inc/widgets/widget-user.php:38` / `:26` |
| 主题文本域 | `style.css:11`（`zib_language`） |
| 主题版本与依赖声明 | `style.css:9-10` |

### 12.3 需特别规避的主题陷阱

| 陷阱 | 后果 | 规避方式 |
| --- | --- | --- |
| 用 `zib_get_option($k, $default, $filter)` 读主题设置 | 参数被忽略，读不到值；主题源码 `inc/options/options.php:323` 有明确警告 | 用 `_pz($name, $default, $subname)` |
| 以为 `zib_get_option_meta_keys` 支持自定义键 | 白名单外的 key 会走原生 `get_option()`，数据散落 | 需要自定义聚合时自行定义 option 键 |
| 调用 `zib_wie_widget_title_for_id_base()` | 该函数内含遗留 `error_log(print_r($wp_widget_factory->widgets, true))`（`widget-import.php:384`），每次调用都dump 整个 widget 工厂 | 自行遍历 `$wp_widget_factory->widgets` 取 `name` |
| 依赖 `inc/code/*.php` | 全部经 `gzinflate()` + `eval()` 混淆（`inc/code/require.php:1,52-62`），无法审计，主题更新即变 | 绝不使用该目录内部实现 |
| 以为主题用了 `template_include` | 主题**完全未使用**该过滤器（全仓检索 0 处），挂上去也不生效 | 用 `template_redirect` + `load_template()` 或页面模板 |
| 继承 `CSF_Options` 等CSF 内部类 | 主题已通过 `csf_override` 覆盖（`inc/options/options.php:166-171`），行为与上游 2.2.0 不同 | 只用 `CSF::createOptions()` / `createSection()` 公开 API |
| 用 `add_role()` / `add_cap()` 建角色 | 主题完全不用 WP capability，插件自建会与主题权限体系割裂 | 用 `user_cap` 矩阵 + `zib_current_user_can()` |
| 期望 `moderator` 身份被 `zib_is_can_roles()` 自动匹配 | 主题默认流程只匹配 all/logged/vip/level/auth，**不含 moderator**（`user-cap.php:233-267`） | 挂 `is_can_roles` 过滤器自行接入 |
| 用 `.btn` 作为按钮 class | 主题主按钮是 `.but`（`.btn` 在 Bootstrap 中语义不同） | 用 `.but` + `.wp-ajax-submit` |
| 用 `.badg` 徽章 | 该 class 在 `widget-options.class.php:649` 被使用但 **`css/main.css` 中无定义**，实际无样式 | 用标准 `.badge` |
| 假设 id_base 有统一前缀 | 前缀不统一：`zib_widget_ui_*` / `widget_ui_*` / `zib_shop_*` / `zib_bbs_*` | 必须读注册对象，禁止前缀推断 |
| 直接改 `$wp_registered_widgets` 而非 `widgets_init` 后期 | CSF 轨在 `after_setup_theme` 就把 id 写进 `CSF::$args`，实际注册在 `widgets_init`（`setup.class.php:65`），过早替换 callback 会被覆盖 | 挂 `widgets_init` 优先级 999 |
| 认为 `$wp_registered_widgets` 就是「已启用」 | 它是「已注册」，主题注册 51 个但站点可能一个都没用 | 判断启用必须读 `wp_get_sidebars_widgets()` |
| 遍历 `widget_{id_base}` option 时不过滤 `_multiwidget` | `_multiwidget` 是字符串键，会被误当实例号处理 | 用 `is_numeric($k)` 过滤（主题在 `widget-import.php:45-49` 如此处理） |
| 依赖 `get_widget_object()` 取小工具实例 | CSF 轨 `register_widgets()` 内部 `new WP_Widget_Factory()`，工厂对象在 `widgets_init` 后不可靠（`widget-options.class.php:81-95` 注释已警告） | 自行遍历 `$wp_widget_factory->widgets` |
| 调用 `zib_newmsg_publish_to_pending()` 前不设 `$_REQUEST['msg_s']` | 该函数第 320 行 `if (empty($_REQUEST['msg_s'])) { return; }` 会**静默失败** | 调用前显式 `$_REQUEST['msg_s'] = 1;` |
| 期望驳回通知发给「作者自己驳回」的场景 | 该函数第 333 行 `$user_id == get_current_user_id()` 时直接 return | 自我审核场景插件自行补发通知 |
| 把配置写进 `zibll_options` | 主题有20 条自动备份与一键重置，误操作会清空插件配置 | 插件配置存独立 option |
| 插件目录被直接删除后仍依赖 `zsr_widget_locked` 判定 | 孤儿 option 导致小工具**永久锁定** | 所有判定前检查 `defined('ZSR_VERSION')` 与文件存在性，异常时自动失效并记日志 |

### 12.4 名词与代码对照速查

| 文档用语 | 实际代码 |
| --- | --- |
| 能力键 | capability，`zibll_options['user_cap']` 的键 |
| 身份 | role / 身份项，8 类 |
| 「已注册」小工具 | `$wp_widget_factory->widgets` 的 key（id_base） |
| 「已启用」小工具 | `wp_get_sidebars_widgets()` 各侧边栏数组中出现的实例 |
| CSF 轨 / 旧轨 | `CSF_Widget` 子类 42 个 / `extends WP_Widget` 9 个 |
| 页面模板 | Page Template，带 `Template Name:` 头部的独立 PHP 文件 |
| 视图 | `?view=` 查询参数对应的页面区块 |
| 状态机 | `post_status` × `post_meta.zsr_state` 组合 |

---

**文档结束**

*本文档基于 Zibll V9.0 主题源码实测编写。主题更新后请复核 §12.2 代码位置索引与 §12.3 陷阱表。*
