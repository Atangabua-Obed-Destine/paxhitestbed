<style>
.mbx { --mbx-ink: #1f2933; --mbx-muted: #616e7c; --mbx-line: #e4e7eb; --mbx-soft: #f5f7fa; --mbx-accent: #2f6fde; --mbx-unread: #e8f0fe; }
.mbx .mbx-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.mbx .mbx-tabs { display: flex; flex-wrap: wrap; gap: .25rem; }
.mbx .mbx-tab { padding: .4rem .85rem; border-radius: 999px; color: var(--mbx-muted); text-decoration: none; font-weight: 500; }
.mbx .mbx-tab:hover { background: var(--mbx-soft); color: var(--mbx-ink); }
.mbx .mbx-tab.active { background: var(--mbx-accent); color: #fff; }
.mbx .mbx-tab .badge { margin-left: .3rem; }
.mbx .mbx-search { display: flex; gap: .35rem; }
.mbx .mbx-search input { min-width: 14rem; }
.mbx .mbx-list { border: 1px solid var(--mbx-line); border-radius: 8px; overflow: hidden; background: #fff; }
.mbx .mbx-row { display: grid; grid-template-columns: 2.5rem minmax(9rem, 14rem) 1fr auto; gap: .75rem; align-items: center; padding: .7rem 1rem; border-top: 1px solid var(--mbx-line); color: var(--mbx-ink); text-decoration: none; }
.mbx .mbx-row:first-child { border-top: 0; }
.mbx .mbx-row:hover { background: var(--mbx-soft); color: var(--mbx-ink); }
.mbx .mbx-row.unread { background: var(--mbx-unread); }
.mbx .mbx-row.unread .mbx-who, .mbx .mbx-row.unread .mbx-subject { font-weight: 700; }
.mbx .mbx-avatar { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #d9e2ec; color: #334e68; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; flex: none; }
.mbx .mbx-avatar.student { background: #e3f8e8; color: #1f6f3f; }
.mbx .mbx-who { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mbx .mbx-who small { display: block; color: var(--mbx-muted); font-weight: 400; }
.mbx .mbx-line { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--mbx-muted); }
.mbx .mbx-subject { color: var(--mbx-ink); }
.mbx .mbx-when { color: var(--mbx-muted); font-size: .85rem; white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }
.mbx .mbx-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; background: var(--mbx-accent); margin-right: .35rem; }
.mbx .mbx-empty { padding: 2.5rem 1rem; text-align: center; color: var(--mbx-muted); }
.mbx .mbx-head { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--mbx-line); padding-bottom: .75rem; margin-bottom: 1rem; }
.mbx .mbx-head h4 { margin: 0 0 .25rem; font-size: 1.25rem; }
.mbx .mbx-people { color: var(--mbx-muted); }
.mbx .mbx-note { background: #fff8e6; border: 1px solid #f7d070; border-radius: 6px; padding: .5rem .75rem; margin-bottom: 1rem; color: #7a5a00; }
.mbx .mbx-msg { display: flex; gap: .75rem; margin-bottom: 1rem; max-width: 52rem; }
.mbx .mbx-msg.mine { margin-left: auto; flex-direction: row-reverse; }
.mbx .mbx-bubble { background: var(--mbx-soft); border: 1px solid var(--mbx-line); border-radius: 10px; padding: .65rem .9rem; min-width: 12rem; }
.mbx .mbx-msg.mine .mbx-bubble { background: #eaf1fd; border-color: #c9dbf8; }
.mbx .mbx-meta { font-size: .82rem; color: var(--mbx-muted); margin-bottom: .3rem; }
.mbx .mbx-meta strong { color: var(--mbx-ink); }
.mbx .mbx-body { white-space: pre-wrap; word-wrap: break-word; line-height: 1.5; }
.mbx .mbx-files { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .55rem; }
.mbx .mbx-file { display: inline-flex; gap: .35rem; align-items: center; padding: .25rem .6rem; border: 1px solid var(--mbx-line); border-radius: 6px; background: #fff; font-size: .85rem; text-decoration: none; }
.mbx .mbx-reply { border-top: 1px solid var(--mbx-line); padding-top: 1rem; margin-top: .5rem; max-width: 52rem; }
.mbx .mbx-help { color: var(--mbx-muted); font-size: .85rem; }
.mbx .mbx-count { font-variant-numeric: tabular-nums; }
@media (max-width: 767px) {
  .mbx .mbx-row { grid-template-columns: 2.5rem 1fr auto; }
  .mbx .mbx-row .mbx-line { grid-column: 2 / 4; }
  .mbx .mbx-search input { min-width: 0; width: 100%; }
}
</style>
